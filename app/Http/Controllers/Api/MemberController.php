<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ActivityLogged;
use App\Helpers\ResponseFormatter;
use App\Models\Member;
use App\Models\PaymentDetail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }

    /**
     * GET /api/members
     * List members dengan pagination, search, dan sort
     */
    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $query = Member::with(['paymentDetail', 'connection.profile', 'connection.area'])
                ->orderBy('created_at', 'desc');

            // Role-based filtering
            if (in_array($user->role, ['mitra', 'kasir'])) {
                $query->where('group_id', $user->group_id);
            } elseif ($user->role === 'teknisi') {
                $assignedAreaIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id')
                    ->toArray();

                if (empty($assignedAreaIds)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas('connection', function ($q) use ($assignedAreaIds) {
                        $q->whereIn('area_id', $assignedAreaIds);
                    });
                }
            } else {
                $query->where('group_id', $user->group_id);
            }

            // 🔍 Search
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('fullname', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('id_card', 'like', "%{$search}%");
                });
            }

            if ($request->has('area_id') && $request->area_id) {
                $query->where('area_id', $request->area_id);
            }

            // Filter by billing status
            if ($request->has('billing') && $request->billing !== '') {
                $query->where('billing', $request->billing == '1');
            }

            // 🔄 Sort
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = $request->get('per_page', 15);
            $members = $query->paginate($perPage);

            return ResponseFormatter::success($members, 'Data members berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * GET /api/members/{id}
     * Get single member detail
     */
    public function show($id)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $member = Member::with([
                'paymentDetail',
                'connection.profile',
                'connection.area',
                'connection.optical',
                'invoices' => function ($q) {
                    $q->latest()->limit(10);
                }
            ])->findOrFail($id);

            // Check access
            if ($user->role !== 'superadmin' && $member->group_id !== $user->group_id) {
                return response()->json(['message' => 'Member tidak ditemukan!'], 403);
            }

            return ResponseFormatter::success($member, 'Data member berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * PUT /api/members/{id}
     * Update member basic info
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $this->getAuthUser();
            $member = Member::where('id', $id)->firstOrFail();

            if ($member->group_id !== $user->group_id) {
                return response()->json(['message' => 'Member tidak ditemukan!'], 403);
            }

            $oldData = $member->toArray();

            $validated = $request->validate([
                'fullname' => 'required|string|max:255',
                'phone_number' => 'nullable|string|min:9',
                'email' => 'nullable|email',
                'id_card' => 'nullable|string|max:16',
                'address' => 'nullable|string|max:500',
            ]);

            $member->update($validated);

            ActivityLogged::dispatch('UPDATE', $oldData, $member);

            return ResponseFormatter::success($member, 'Member berhasil diperbarui', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * PUT /api/members/{id}/payment-detail
     * Update member payment detail
     */
    public function updatePaymentDetail(Request $request, $id)
    {
        try {
            $user = $this->getAuthUser();
            $member = Member::with('paymentDetail')->findOrFail($id);

            if ($member->group_id !== $user->group_id) {
                return response()->json(['message' => 'Member tidak ditemukan!'], 403);
            }

            $validated = $request->validate([
                'payment_type' => 'required|in:prabayar,pascabayar',
                'billing_period' => 'required|in:fixed,renewal',
                'active_date' => 'required|date',
                'amount' => 'required|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'ppn' => 'nullable|numeric|min:0|max:100',
            ]);

            $payload = array_merge($validated, [
                'group_id' => $user->group_id
            ]);

            DB::beginTransaction();

            if ($member->paymentDetail) {
                $oldData = $member->paymentDetail->toArray();
                $member->paymentDetail->update($payload);

                ActivityLogged::dispatch('UPDATE', $oldData, $member->paymentDetail);
            } else {
                $paymentDetail = PaymentDetail::create($payload);
                $member->update(['payment_detail_id' => $paymentDetail->id]);

                ActivityLogged::dispatch('CREATE', null, $paymentDetail);
            }

            DB::commit();

            return ResponseFormatter::success(
                $member->fresh(['paymentDetail']),
                'Payment detail berhasil diperbarui',
                200
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * GET /api/members/{id}/invoices
     * Get member invoices
     */
    public function getInvoices($id)
    {
        try {
            $user = $this->getAuthUser();
            $member = Member::findOrFail($id);

            if ($member->group_id !== $user->group_id) {
                return response()->json(['message' => 'Member tidak ditemukan!'], 403);
            }

            $invoices = $member->invoices()
                ->with(['connection.profile'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return ResponseFormatter::success($invoices, 'Invoices berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * GET /api/members/stats
     * Get members statistics
     */
    public function stats()
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $query = Member::where('group_id', $user->group_id);

            $stats = [
                'total' => (clone $query)->count(),
                'with_billing' => (clone $query)->where('billing', true)->count(),
                'without_billing' => (clone $query)->where('billing', false)->count(),
            ];

            return ResponseFormatter::success($stats, 'Statistics berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
