<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ActivityLogged;
use App\Helpers\ResponseFormatter;
use App\Helpers\InvoiceHelper;
use App\Models\AccountingTransaction;
use App\Models\Connection;
use App\Models\GlobalSettings;
use App\Models\InvoiceHomepass;
use App\Models\Member;
use App\Models\PaymentDetail;
use App\Models\User;
use App\Services\WhatsappService;
use App\Jobs\GenerateAllInvoiceJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;

class InvoiceController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsappService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
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

    private function getApiKey($groupId)
    {
        $settings = GlobalSettings::where('group_id', $groupId)->first();
        return $settings->whatsapp_api_key ?? null;
    }

    /**
     * GET /api/invoices
     * List invoices dengan pagination, search, dan filter
     */
    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $query = InvoiceHomepass::with([
                'member.paymentDetail',
                'member.connection.profile',
                'member.connection.area',
                'payer'
            ])->latest();

            // Role-based filtering
            if ($user->role === 'teknisi') {
                $assignedAreaIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id')
                    ->toArray();

                if (empty($assignedAreaIds)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas('member.connection', function ($q) use ($assignedAreaIds) {
                        $q->whereIn('area_id', $assignedAreaIds);
                    });
                }
            } else {
                $query->where('group_id', $user->group_id);
            }

            // 🔍 Search
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('inv_number', 'like', "%{$search}%")
                        ->orWhereHas('member', function ($q2) use ($search) {
                            $q2->where('fullname', 'like', "%{$search}%");
                        });
                });
            }

            // Filter by status
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Filter by payment type
            if ($request->has('type') && $request->type) {
                $query->whereHas('member.paymentDetail', function ($q) use ($request) {
                    $q->where('payment_type', strtolower($request->type));
                });
            }

            // Filter by payer role
            if ($request->has('payer') && $request->payer) {
                if ($request->payer === 'kasir') {
                    $query->whereHas('payer', fn($q) => $q->where('role', 'kasir'));
                } elseif ($request->payer === 'admin') {
                    $query->whereHas('payer', fn($q) => $q->where('role', 'mitra'));
                } elseif ($request->payer === 'teknisi') {
                    $query->whereHas('payer', fn($q) => $q->where('role', 'teknisi'));
                }
            }

            // Filter by area
            if ($request->has('area_id') && $request->area_id) {
                $query->whereHas('member.connection', function ($q) use ($request) {
                    $q->where('area_id', $request->area_id);
                });
            }

            // Date Range Filter
            if ($request->has('date_from') && $request->date_from) {
                $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
            }
            if ($request->has('date_to') && $request->date_to) {
                $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
            }

            // 🔄 Sort
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = $request->get('per_page', 15);
            $invoices = $query->paginate($perPage);

            return ResponseFormatter::success($invoices, 'Data invoices berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * GET /api/invoices/stats
     * Get invoice statistics
     */
    public function stats(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $now = Carbon::now();
            $startOfThisMonth = Carbon::now()->startOfMonth();

            $baseQuery = InvoiceHomepass::query();

            // Role-based filtering
            if ($user->role === 'teknisi') {
                $assignedAreaIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id')
                    ->toArray();

                if (!empty($assignedAreaIds)) {
                    $baseQuery->whereHas('member.connection', function ($q) use ($assignedAreaIds) {
                        $q->whereIn('area_id', $assignedAreaIds);
                    });
                } else {
                    $baseQuery->whereRaw('1 = 0');
                }
            } else {
                $baseQuery->where('group_id', $user->group_id);
            }

            $stats = [
                'this_month' => [
                    'total_invoices' => (clone $baseQuery)
                        ->whereMonth('created_at', $now->month)
                        ->whereYear('created_at', $now->year)
                        ->count(),
                    'total_amount' => (clone $baseQuery)
                        ->whereMonth('created_at', $now->month)
                        ->whereYear('created_at', $now->year)
                        ->sum('amount'),
                    'paid_count' => (clone $baseQuery)
                        ->where('status', 'paid')
                        ->whereMonth('paid_at', $now->month)
                        ->whereYear('paid_at', $now->year)
                        ->count(),
                    'paid_amount' => (clone $baseQuery)
                        ->where('status', 'paid')
                        ->whereMonth('paid_at', $now->month)
                        ->whereYear('paid_at', $now->year)
                        ->sum('amount'),
                    'unpaid_count' => (clone $baseQuery)
                        ->where('status', 'unpaid')
                        ->whereMonth('created_at', $now->month)
                        ->whereYear('created_at', $now->year)
                        ->count(),
                    'unpaid_amount' => (clone $baseQuery)
                        ->where('status', 'unpaid')
                        ->whereMonth('created_at', $now->month)
                        ->whereYear('created_at', $now->year)
                        ->sum('amount'),
                ],
                'overdue' => [
                    'count' => (clone $baseQuery)
                        ->where('status', 'unpaid')
                        ->where('created_at', '<', $startOfThisMonth)
                        ->count(),
                    'amount' => (clone $baseQuery)
                        ->where('status', 'unpaid')
                        ->where('created_at', '<', $startOfThisMonth)
                        ->sum('amount'),
                ],
            ];

            return ResponseFormatter::success($stats, 'Statistics berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * GET /api/invoices/date-range-stats
     * Statistics dengan custom date range
     */
    public function getDateRangeStats(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');

            $baseQuery = InvoiceHomepass::query();

            // Role-based filtering
            if ($user->role === 'teknisi') {
                $assignedAreaIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id')
                    ->toArray();

                if (!empty($assignedAreaIds)) {
                    $baseQuery->whereHas('member.connection', function ($q) use ($assignedAreaIds) {
                        $q->whereIn('area_id', $assignedAreaIds);
                    });
                } else {
                    $baseQuery->whereRaw('1 = 0');
                }
            } else {
                $baseQuery->where('group_id', $user->group_id);
            }

            // Apply date range
            if ($dateFrom && $dateTo) {
                $baseQuery->whereBetween('created_at', [
                    Carbon::parse($dateFrom)->startOfDay(),
                    Carbon::parse($dateTo)->endOfDay()
                ]);
            }

            $stats = [
                'total_invoices' => (clone $baseQuery)->count(),
                'total_amount' => (clone $baseQuery)->sum('amount'),
                'paid_count' => (clone $baseQuery)->where('status', 'paid')->count(),
                'paid_amount' => (clone $baseQuery)->where('status', 'paid')->sum('amount'),
                'unpaid_count' => (clone $baseQuery)->where('status', 'unpaid')->count(),
                'unpaid_amount' => (clone $baseQuery)->where('status', 'unpaid')->sum('amount'),
                'overdue_count' => (clone $baseQuery)
                    ->where('status', 'unpaid')
                    ->where('due_date', '<', Carbon::now())
                    ->count(),
                'overdue_amount' => (clone $baseQuery)
                    ->where('status', 'unpaid')
                    ->where('due_date', '<', Carbon::now())
                    ->sum('amount'),
            ];

            return ResponseFormatter::success($stats, 'Statistics berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * POST /api/invoices/create
     * Create invoice untuk member
     */
    public function createInv(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $validated = $request->validate([
                'member_id' => 'required|exists:members,id',
                'subs_periode' => 'required|integer|min:1',
                'due_date' => 'required|date',
                'periode' => 'required|string',
                'amount' => 'required|numeric|min:0',
            ]);

            $member = Member::with('paymentDetail', 'connection.profile')
                ->where('id', $validated['member_id'])
                ->where('billing', 1)
                ->firstOrFail();

            if ($member->group_id !== $user->group_id) {
                return response()->json(['message' => 'Member tidak ditemukan!'], 403);
            }

            // ✅ Pakai method generateInvoice()
            $invoice = $member->paymentDetail->generateInvoice(
                $validated['subs_periode'],
                $validated['due_date']
            );

            ActivityLogged::dispatch('CREATE', null, $invoice);

            return ResponseFormatter::success($invoice, 'Invoice berhasil dibuat', 201);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }


    /**
     * POST /api/invoices/generate-all
     * Generate invoices untuk semua member aktif
     */
    public function generateAll(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            //  Pakai method needsInvoiceGeneration()
            $paymentDetails = PaymentDetail::with('member.connection')
                ->where('group_id', $user->group_id)
                ->get();

            $generated = 0;

            foreach ($paymentDetails as $pd) {
                if ($pd->needsInvoiceGeneration()) {
                    GenerateAllInvoiceJob::dispatch($pd->member)->onQueue('invoices');
                    $generated++;
                }
            }

            if ($generated === 0) {
                return ResponseFormatter::success(
                    null,
                    'Semua pelanggan sudah memiliki invoice sampai bulan ini',
                    200
                );
            }

            return ResponseFormatter::success(
                ['count' => $generated],
                "Proses generate invoice sedang berjalan di background",
                200
            );
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }


    /**
     * POST /api/invoices/{id}/pay-manual
     * Manual payment (cash/bank_transfer)
     */
    public function payManual(Request $request, $id)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $validated = $request->validate([
                'payment_method' => 'required|in:cash,bank_transfer',
            ]);

            $invoice = InvoiceHomepass::with(['member.paymentDetail', 'connection.profile'])
                ->findOrFail($id);

            if ($invoice->group_id !== $user->group_id) {
                return response()->json(['message' => 'Invoice tidak ditemukan!'], 403);
            }

            DB::beginTransaction();

            $invoice->update([
                'status' => 'paid',
                'payer_id' => $user->id,
                'payment_method' => $validated['payment_method'],
                'paid_at' => now()
            ]);

            // Create accounting transaction
            AccountingTransaction::create([
                'group_id' => $invoice->group_id,
                'transaction_type' => 'income',
                'category' => 'subscription_payment',
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->inv_number,
                'member_name' => $invoice->member->fullname,
                'amount' => $invoice->amount,
                'payment_method' => $validated['payment_method'],
                'received_by' => $user->id,
                'transaction_date' => now(),
                'description' => 'Pembayaran invoice ' . $invoice->inv_number,
                'notes' => 'Pembayaran invoice periode ' . $invoice->subscription_period,
            ]);

            // Update last_invoice
            if ($invoice->member && $invoice->member->paymentDetail) {
                $dueDate = Carbon::parse($invoice->due_date);
                PaymentDetail::where('id', $invoice->member->payment_detail_id)->update([
                    'last_invoice' => $dueDate->format('Y-m-d'),
                ]);
            }

            DB::commit();

            ActivityLogged::dispatch('UPDATE', null, $invoice);

            // Send WhatsApp notification
            $apiKey = $this->getApiKey($user->group_id);
            if ($apiKey) {
                $methodMap = [
                    'cash' => 'Cash',
                    'bank_transfer' => 'Bank Transfer',
                    'payment_gateway' => 'Payment Gateway',
                ];

                $footer = GlobalSettings::where('group_id', $user->group_id)->value('footer');

                // Load connection relationship safely
                if (!$invoice->relationLoaded('connection')) {
                    $invoice->load('connection.profile');
                }
                $connection = $invoice->getRelation('connection');

                $this->whatsappService->sendFromTemplate(
                    $apiKey,
                    $invoice->member->phone_number,
                    'payment_paid',
                    [
                        'full_name' => $invoice->member->fullname,
                        'no_invoice' => $invoice->inv_number,
                        'total' => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                        'pppoe_user' => $connection?->username ?? '-',
                        'pppoe_profile' => $connection?->profile?->name ?? '-',
                        'period' => $invoice->subscription_period,
                        'payment_gateway' => $methodMap[$validated['payment_method']] ?? ucfirst($validated['payment_method']),
                        'footer' => $footer
                    ],
                    ['group_id' => $invoice->group_id]
                );
            }

            return ResponseFormatter::success($invoice, 'Pembayaran berhasil!', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * POST /api/invoices/{id}/cancel-payment
     * Cancel payment
     */
    public function payCancel(Request $request, $id)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $invoice = InvoiceHomepass::with(['member.paymentDetail'])->findOrFail($id);

            if ($invoice->group_id !== $user->group_id) {
                return response()->json(['message' => 'Invoice tidak ditemukan!'], 403);
            }

            DB::beginTransaction();

            // Delete accounting transaction
            AccountingTransaction::where('invoice_id', $invoice->id)
                ->where('transaction_type', 'income')
                ->delete();

            // Rollback last_invoice
            if ($invoice->member && $invoice->member->paymentDetail) {
                $previousPaidInvoice = InvoiceHomepass::where('member_id', $invoice->member_id)
                    ->where('status', 'paid')
                    ->where('id', '!=', $invoice->id)
                    ->orderByDesc('due_date')
                    ->first();

                PaymentDetail::where('id', $invoice->member->payment_detail_id)->update([
                    'last_invoice' => $previousPaidInvoice ? $previousPaidInvoice->due_date : null,
                ]);
            }

            // Update invoice
            $invoice->update([
                'status' => 'unpaid',
                'payer_id' => null,
                'payment_method' => null,
                'paid_at' => null
            ]);

            DB::commit();

            ActivityLogged::dispatch('UPDATE', null, $invoice);

            // Send WhatsApp notification
            $apiKey = $this->getApiKey($user->group_id);
            if ($apiKey) {
                $this->whatsappService->sendFromTemplate(
                    $apiKey,
                    $invoice->member->phone_number,
                    'payment_cancel',
                    [
                        'full_name' => $invoice->member->fullname,
                        'no_invoice' => $invoice->inv_number,
                        'total' => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                        'invoice_date' => $invoice->start_date,
                        'due_date' => $invoice->due_date,
                        'period' => $invoice->subscription_period,
                    ],
                    ['group_id' => $invoice->group_id]
                );
            }

            return ResponseFormatter::success($invoice, 'Cancel berhasil!', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/invoices/{id}
     * Delete invoice
     */
    public function destroy($id)
    {
        try {
            $user = $this->getAuthUser();
            $invoice = InvoiceHomepass::findOrFail($id);

            if ($invoice->group_id !== $user->group_id) {
                return response()->json(['message' => 'Invoice tidak ditemukan!'], 403);
            }

            $member = Member::findOrFail($invoice->member_id);

            DB::beginTransaction();

            $deletedData = $invoice->toArray();
            $invoice->delete();

            // Update last_invoice
            $lastInvoice = InvoiceHomepass::where('member_id', $member->id)
                ->orderByDesc('due_date')
                ->first();

            PaymentDetail::where('id', $member->payment_detail_id)->update([
                'last_invoice' => $lastInvoice ? $lastInvoice->due_date : null,
            ]);

            DB::commit();

            ActivityLogged::dispatch('DELETE', null, $deletedData);

            return ResponseFormatter::success($deletedData, 'Invoice berhasil dihapus', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * POST /api/xendit/callback
     * Xendit payment webhook (no auth required)
     */
    public function xenditCallback(Request $request)
    {
        $callbackToken = $request->header('X-CALLBACK-TOKEN');

        if ($callbackToken !== env('XENDIT_CALLBACK_TOKEN')) {
            Log::warning('Invalid Xendit callback token');
            return response()->json(['message' => 'Invalid callback token'], 401);
        }

        DB::beginTransaction();
        try {
            $externalId = $request->input('external_id');
            $status = $request->input('status');
            $paidAmount = $request->input('paid_amount');
            $paidAt = $request->input('paid_at');

            Log::info('Xendit Callback Received', [
                'external_id' => $externalId,
                'status' => $status,
                'paid_amount' => $paidAmount
            ]);

            if ($status === 'PAID') {
                $invoice = InvoiceHomepass::with(['member.paymentDetail', 'connection.profile'])
                    ->where('inv_number', $externalId)
                    ->firstOrFail();

                $invoice->update([
                    'status' => 'paid',
                    'payment_method' => 'payment_gateway',
                    'paid_at' => $paidAt ? Carbon::parse($paidAt) : now()
                ]);

                AccountingTransaction::create([
                    'group_id' => $invoice->group_id,
                    'transaction_type' => 'income',
                    'category' => 'subscription_payment',
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->inv_number,
                    'member_name' => $invoice->member->fullname,
                    'amount' => $paidAmount,
                    'payment_method' => 'payment_gateway',
                    'received_by' => null,
                    'transaction_date' => $paidAt ? Carbon::parse($paidAt) : now(),
                    'description' => 'Pembayaran invoice via Payment Gateway',
                    'notes' => 'Pembayaran otomatis via Xendit - ' . $invoice->subscription_period,
                ]);

                if ($invoice->member && $invoice->member->paymentDetail) {
                    $dueDate = Carbon::parse($invoice->due_date);
                    PaymentDetail::where('id', $invoice->member->payment_detail_id)->update([
                        'last_invoice' => $dueDate->format('Y-m-d'),
                    ]);
                }

                DB::commit();

                // Send notification
                $apiKey = $this->getApiKey($invoice->group_id);
                if ($apiKey) {
                    $footer = GlobalSettings::where('group_id', $invoice->group_id)->value('footer');

                    // Get connection relationship safely
                    $connection = $invoice->getRelation('connection');

                    $this->whatsappService->sendFromTemplate(
                        $apiKey,
                        $invoice->member->phone_number,
                        'payment_paid',
                        [
                            'full_name' => $invoice->member->fullname,
                            'no_invoice' => $invoice->inv_number,
                            'total' => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                            'pppoe_user' => $connection?->username ?? '-',
                            'pppoe_profile' => $connection?->profile?->name ?? '-',
                            'period' => $invoice->subscription_period,
                            'payment_gateway' => 'Payment Gateway',
                            'footer' => $footer
                        ],
                        ['group_id' => $invoice->group_id]
                    );
                }

                Log::info('Xendit Callback Processed Successfully', ['invoice' => $externalId]);
            }

            return response()->json(['message' => 'Callback processed'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Xendit callback error: ' . $e->getMessage());
            return response()->json(['message' => 'Error processing callback'], 500);
        }
    }
}
