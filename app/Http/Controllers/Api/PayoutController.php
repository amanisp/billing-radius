<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
use App\Events\ActivityLogged;
use App\Helpers\ResponseFormatter;
use App\Models\GlobalSettings;
use App\Models\Payout;
use App\Models\User;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Xendit\Configuration;

class PayoutController extends Controller
{
    public function __construct()
    {
        Configuration::setXenditKey(env('XENDIT_SECRET_KEY'));
    }

    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }

    /**
     * GET /api/payouts
     * List payouts dengan pagination, search, dan filter
     */
    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $query = Payout::where('group_id', $user->group_id);

            // 🔍 Search by external_id or email
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('external_id', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filter by status
            if ($request->has('status') && $request->status) {
                $query->where('status', strtoupper($request->status));
            }

            // 🔄 Sort
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = $request->get('per_page', 15);
            $payouts = $query->paginate($perPage);

            // Transform expiration timestamp
            $payouts->getCollection()->transform(function ($payout) {
                if ($payout->exp_link) {
                    try {
                        $date = new DateTime($payout->exp_link, new DateTimeZone("UTC"));
                        $date->setTimezone(new DateTimeZone("Asia/Jakarta"));
                        $payout->exp_link_formatted = $date->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        $payout->exp_link_formatted = $payout->exp_link;
                    }
                }
                return $payout;
            });

            ActivityLogController::logCreate([
                'action' => 'view_payouts_list',
                'total_records' => $payouts->total(),
                'status' => 'success'
            ], 'payouts');
            return ResponseFormatter::success($payouts, 'Data payouts berhasil dimuat');
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'view_payouts_list', 'error' => $th->getMessage()], 'payouts');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * GET /api/payouts/stats
     * Get payout statistics
     */
    public function stats(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $query = Payout::where('group_id', $user->group_id);

            $stats = [
                'total' => (clone $query)->count(),
                'total_amount' => (clone $query)->sum('amount'),
                'pending' => (clone $query)->where('status', 'PENDING')->count(),
                'pending_amount' => (clone $query)->where('status', 'PENDING')->sum('amount'),
                'completed' => (clone $query)->where('status', 'COMPLETED')->count(),
                'completed_amount' => (clone $query)->where('status', 'COMPLETED')->sum('amount'),
                'failed' => (clone $query)->where('status', 'FAILED')->count(),
                'failed_amount' => (clone $query)->where('status', 'FAILED')->sum('amount'),
            ];

            ActivityLogController::logCreate([
                'action' => 'view_payouts_stats',
                'stats' => $stats,
                'status' => 'success'
            ], 'payouts');
            return ResponseFormatter::success($stats, 'Statistics berhasil dimuat');
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'view_payouts_stats', 'error' => $th->getMessage()], 'payouts');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * GET /api/payouts/{id}
     * Get single payout detail
     */
    public function show($id)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $payout = Payout::where('id', $id)
                ->where('group_id', $user->group_id)
                ->firstOrFail();

            // Format expiration timestamp
            if ($payout->exp_link) {
                try {
                    $date = new DateTime($payout->exp_link, new DateTimeZone("UTC"));
                    $date->setTimezone(new DateTimeZone("Asia/Jakarta"));
                    $payout->exp_link_formatted = $date->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $payout->exp_link_formatted = $payout->exp_link;
                }
            }

            ActivityLogController::logCreate([
                'payout_id' => $id,
                'action' => 'view_payout_detail',
                'status' => 'success'
            ], 'payouts');
            return ResponseFormatter::success($payout, 'Data payout berhasil dimuat');
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['payout_id' => $id ?? null, 'action' => 'view_payout_detail', 'error' => $th->getMessage()], 'payouts');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * POST /api/payouts
     * Create payout request via Xendit
     */
    public function createPayout(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $validated = $request->validate([
                'email' => 'required|email',
                'amount' => 'required|numeric|min:10000', // Minimum Rp 10.000
            ]);

            // Generate unique reference ID
            $id = bin2hex(random_bytes(10));
            $externalId = $id . '_' . $user->group_id;

            Log::info('Creating payout', [
                'external_id' => $externalId,
                'email' => $validated['email'],
                'amount' => $validated['amount'],
                'group_id' => $user->group_id,
            ]);

            // Create payout via Xendit API
            $response = Http::withBasicAuth(env('XENDIT_SECRET_KEY'), '')
                ->asForm()
                ->post('https://api.xendit.co/payouts', [
                    'external_id' => $externalId,
                    'amount' => $validated['amount'],
                    'email' => $validated['email'],
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Save to database
                $payout = Payout::create([
                    'payout_url' => $data['payout_url'],
                    'email' => $data['email'],
                    'exp_link' => $data['expiration_timestamp'],
                    'external_id' => $data['external_id'],
                    'amount' => $data['amount'],
                    'status' => $data['status'],
                    'group_id' => $user->group_id,
                ]);

            ActivityLogController::logCreate(['action' => 'createPayout', 'status' => 'success'], 'payouts');
            Log::info('Payout created successfully', [
                'payout_id' => $payout->id,
                'external_id' => $payout->external_id,
            ]);

            return ResponseFormatter::success([
                'payout' => $payout,
                'xendit_response' => $data,
            ], 'Payout berhasil dibuat', 201);
        } else {
            $errorBody = $response->body();
            ActivityLogController::logCreateF(['action' => 'createPayout', 'error' => $errorBody], 'payouts');
            Log::error('Xendit payout creation failed', [
                'status' => $response->status(),
                'error' => $errorBody,
            ]);

            return ResponseFormatter::error(
                ['xendit_error' => $errorBody],
                'Gagal membuat payout di Xendit',
                $response->status()
            );
        }
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'createPayout', 'error' => $th->getMessage()], 'payouts');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * POST /api/payouts/{id}/check-status
     * Check payout status from Xendit
     */
    public function checkStatus($id)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $payout = Payout::where('id', $id)
                ->where('group_id', $user->group_id)
                ->firstOrFail();

            // Query Xendit API for payout status
            $response = Http::withBasicAuth(env('XENDIT_SECRET_KEY'), '')
                ->get("https://api.xendit.co/payouts/{$payout->external_id}");

            if ($response->successful()) {
                $data = $response->json();

                // Update local status if changed
                if ($payout->status !== $data['status']) {
                    $payout->update(['status' => $data['status']]);
                }

                ActivityLogController::logCreate(['payout_id' => $id, 'action' => 'check_payout_status', 'new_status' => $data['status'], 'status' => 'success'], 'payouts');

                return ResponseFormatter::success([
                    'payout' => $payout->fresh(),
                    'xendit_data' => $data,
                ], 'Status payout berhasil diupdate');
            } else {
                ActivityLogController::logCreateF(['action' => 'checkStatus', 'error' => $response->body()], 'payouts');
                return ResponseFormatter::error(
                    ['xendit_error' => $response->body()],
                    'Gagal mengecek status payout',
                    $response->status()
                );
            }
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'checkStatus', 'error' => $th->getMessage()], 'payouts');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/payouts/{id}
     * Delete payout record (only for pending/failed)
     */
    public function destroy($id)
    {
        try {
            $user = $this->getAuthUser();
            $payout = Payout::where('id', $id)
                ->where('group_id', $user->group_id)
                ->firstOrFail();

            // Only allow deletion of PENDING or FAILED payouts
            if ($payout->status === 'COMPLETED') {
                return response()->json([
                    'message' => 'Tidak dapat menghapus payout yang sudah completed'
                ], 403);
            }

            $deletedData = $payout->toArray();
            $payout->delete();

            ActivityLogController::logCreate([
                'action' => 'delete_payout',
                'payout_id' => $id,
                'external_id' => $deletedData['external_id'],
                'status' => 'success'
            ], 'payouts');
            return ResponseFormatter::success($deletedData, 'Payout berhasil dihapus', 200);
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'destroy', 'error' => $th->getMessage()], 'payouts');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * POST /api/payouts/xendit-callback
     * Xendit payout webhook callback
     */
    public function xenditCallback(Request $request)
    {
        try {
            $callbackToken = $request->header('X-CALLBACK-TOKEN');

            if ($callbackToken !== env('XENDIT_CALLBACK_TOKEN')) {
                Log::warning('Invalid Xendit payout callback token');
                return response()->json(['message' => 'Invalid callback token'], 401);
            }

            $externalId = $request->input('external_id');
            $status = $request->input('status');
            $amount = $request->input('amount');

            Log::info('Xendit Payout Callback Received', [
                'external_id' => $externalId,
                'status' => $status,
                'amount' => $amount,
            ]);

            $payout = Payout::where('external_id', $externalId)->first();

            if (!$payout) {
                Log::warning('Payout not found for callback', ['external_id' => $externalId]);
                return response()->json(['message' => 'Payout not found'], 404);
            }

            // Update status
            $payout->update(['status' => $status]);

            Log::info('Payout status updated', [
                'payout_id' => $payout->id,
                'new_status' => $status,
            ]);

            ActivityLogController::logCreate(['action' => 'xenditCallback', 'status' => 'success'], 'payouts');
            return response()->json(['message' => 'Callback processed'], 200);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF(['action' => 'xenditCallback', 'error' => $e->getMessage()], 'payouts');
            Log::error('Xendit payout callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Error processing callback'], 500);
        }
    }
}
