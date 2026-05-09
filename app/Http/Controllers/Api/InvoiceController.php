<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
use App\Jobs\BulkInvoiceJob;
use App\Models\GlobalSettings;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\WhatsappCoreService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{

    protected $whatsapp;
    protected $invoiceService;

    public function __construct(InvoiceService $invoiceService, WhatsappCoreService $whatsapp)
    {
        $this->invoiceService = $invoiceService;
        $this->whatsapp = $whatsapp;
    }

    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }


    public function stats(Request $request)
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            /**
             * base query
             */
            $query = Invoice::query()
                ->where('group_id', $user->group_id);

            /**
             * filter area untuk teknisi/kasir
             */
            if (in_array($user->role, ['teknisi', 'kasir'])) {

                $areaIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id');

                if ($areaIds->isEmpty()) {

                    return ResponseFormatter::success([
                        'paid' => [
                            'count'  => 0,
                            'amount' => 0,
                        ],
                        'unpaid' => [
                            'count'  => 0,
                            'amount' => 0,
                        ],
                        'overdue' => [
                            'count'  => 0,
                            'amount' => 0,
                        ],
                        'revenue' => 0,
                    ], 'Tidak ada area yang ditugaskan');
                }

                $query->whereHas('connection', function ($q) use ($areaIds) {
                    $q->whereIn('area_id', $areaIds);
                });
            }

            /**
             * clone query
             */
            $paidQuery = clone $query;
            $unpaidQuery = clone $query;
            $overdueQuery = clone $query;

            /**
             * PAID
             */
            $paidCount = $paidQuery
                ->where('status', 'paid')
                ->count();

            $paidAmount = $paidQuery
                ->where('status', 'paid')
                ->sum('amount');

            /**
             * UNPAID
             */
            $unpaidCount = $unpaidQuery
                ->where('status', 'unpaid')
                ->count();

            $unpaidAmount = $unpaidQuery
                ->where('status', 'unpaid')
                ->sum('amount');

            /**
             * OVERDUE
             */
            $overdueCount = $overdueQuery
                ->where('status', 'unpaid')
                ->whereDate('due_date', '<', now())
                ->count();

            $overdueAmount = $overdueQuery
                ->where('status', 'unpaid')
                ->whereDate('due_date', '<', now())
                ->sum('amount');

            /**
             * TOTAL REVENUE
             */
            $totalRevenue = Invoice::where(
                'group_id',
                $user->group_id
            )
                ->where('invoice_type', 'H')
                ->where('status', 'paid')
                ->sum('amount');

            $stats = [

                'paid' => [
                    'count'  => $paidCount,
                    'amount' => $paidAmount,
                ],

                'unpaid' => [
                    'count'  => $unpaidCount,
                    'amount' => $unpaidAmount,
                ],

                'overdue' => [
                    'count'  => $overdueCount,
                    'amount' => $overdueAmount,
                ],

                'revenue' => $totalRevenue,
            ];

            return ResponseFormatter::success(
                $stats,
                'Stats invoice berhasil dimuat'
            );
        } catch (\Throwable $th) {

            return ResponseFormatter::error(
                null,
                $th->getMessage(),
                500
            );
        }
    }


    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $query = Invoice::with(['member.paymentDetail', 'connection.area', 'connection.profile'])
                ->where('group_id', $user->group_id)
                ->where('status', 'unpaid');

            /**
             * 🔍 Search
             */
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('inv_number', 'like', "%{$search}%")
                        ->orWhereHas('member', function ($m) use ($search) {
                            $m->where('fullname', 'like', "%{$search}%");
                        });
                });
            }

            /**
             * 📅 Filter bulan & tahun
             */
            if ($month = $request->get('month')) {
                $query->whereMonth('start_date', $month);
            }

            if ($year = $request->get('year')) {
                $query->whereYear('start_date', $year);
            }

            /**
             * 📍 Filter area
             */
            if ($areaId = $request->get('area_id')) {
                $query->whereHas('connection', function ($q) use ($areaId) {
                    $q->where('area_id', $areaId);
                });
            }

            /**
             * 🔄 Sort
             */
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            /**
             * 📄 Pagination
             */
            $perPage = $request->get('per_page', 10);
            $invoices = $query->paginate($perPage);

            return ResponseFormatter::success(
                $invoices,
                'Data invoice berhasil dimuat'
            );
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function memberInvoices($memberId)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            $invoices = Invoice::with([
                'member.paymentDetail',
                'connection.area',
                'connection.profile'
            ])
                ->where('group_id', $user->group_id)
                ->where('member_id', $memberId)
                ->where('status', 'unpaid')
                ->orderBy('created_at', 'desc')
                ->get();

            return ResponseFormatter::success(
                $invoices,
                'Data invoice member berhasil dimuat'
            );
        } catch (\Throwable $th) {
            return ResponseFormatter::error(
                null,
                $th->getMessage(),
                500
            );
        }
    }


    public function invoicePaid(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // ========================
            // Base query
            // ========================
            $query = Invoice::with(['payer', 'member'])
                ->where('group_id', $user->group_id)->where('status', 'paid');

            // ========================
            // Target month & year filter
            // ========================
            if ($request->filled('month') && $request->filled('year')) {
                $month = $request->month;
                $year  = $request->year;

                $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
                $endOfMonth   = Carbon::create($year, $month, 1)->endOfMonth();

                $query->whereDate('start_date', '<=', $endOfMonth)
                    ->whereDate('due_date', '>=', $startOfMonth);
            }

            // ========================
            // Search filter (nama / username / internet_number)
            // ========================
            if ($request->filled('search')) {
                $search = $request->search;

                $query->whereHas('member', function ($q) use ($search) {
                    $q->where('fullname', 'LIKE', "%{$search}%");
                });
            }

            // ========================
            // Payer filter
            // ========================
            if ($request->filled('payer_id')) {
                $query->where('payer_id', $request->payer_id);
            } elseif ($request->filled('payer_name')) {
                $query->whereHas('payer', fn($q) => $q->where('fullname', 'like', "%{$request->payer_name}%"));
            }


            // ========================
            // Sorting & pagination
            // ========================
            $query->orderBy(
                $request->get('sort_field', 'created_at'),
                $request->get('sort_direction', 'desc')
            );

            $invoices = $query->paginate($request->get('per_page', 5));

            // ========================
            // Return response
            // ========================
            return ResponseFormatter::success([
                'items' => $invoices->items(),
                'meta' => [
                    'current_page' => $invoices->currentPage(),
                    'per_page'     => $invoices->perPage(),
                    'total'        => $invoices->total(),
                    'last_page'    => $invoices->lastPage(),
                ]
            ], 'Detail invoice berhasil dimuat');
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF([
                'member_id' => $request->get('member_id') ?? null,
                'action' => 'view_member_invoices',
                'error' => $th->getMessage()
            ], 'invoices');

            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $validated = $request->validate([
                'member_id'           => 'required|exists:members,id',
                'amount'              => 'required|numeric|min:0',
                'start_month_year'    => 'required|date_format:Y-m',
                'subscription_period' => 'required|integer|min:1',
            ]);

            $invoices = $this->invoiceService->createInvoices($validated);

            if (empty($invoices)) {
                throw new \Exception("Invoice gagal dibuat.");
            }

            /**
             * Ambil invoice pertama untuk activity log
             */
            $firstInvoice = $invoices[0];

            ActivityLogController::logCreate([
                'action'     => 'create_invoice',
                'inv_number' => $firstInvoice->inv_number,
                'status'     => 'success',
            ], 'invoices');

            return ResponseFormatter::success(
                $invoices,
                'Invoice berhasil dibuat',
                201
            );
        } catch (\Exception $e) {

            ActivityLogController::logCreateF([
                'action' => 'create_invoice',
                'error'  => $e->getMessage(),
            ], 'invoices');

            return ResponseFormatter::error(
                null,
                $e->getMessage(),
                400
            );
        } catch (\Throwable $th) {

            ActivityLogController::logCreateF([
                'action' => 'create_invoice',
                'error'  => $th->getMessage(),
            ], 'invoices');

            return ResponseFormatter::error(
                null,
                $th->getMessage(),
                500
            );
        }
    }


    public function bulkInv(Request $request)
    {
        try {

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validated = $request->validate([
                'start_month_year'    => 'required|date_format:Y-m',
            ]);

            $globalSetting = GlobalSettings::where('group_id', $user->group_id)->first();
            /**
             * Ambil semua member sesuai group user
             */
            $members = Member::with([
                'connection.area',
                'paymentDetail'
            ])
                ->where('group_id', $user->group_id)
                ->get();

            if ($members->isEmpty()) {
                return ResponseFormatter::error(null, 'Member tidak ditemukan', 404);
            }

            $dispatchedCount = 0;
            $delayInSeconds = 0; // Variabel untuk mengatur jeda

            foreach ($members as $member) {
                if (!$member->paymentDetail) {
                    continue;
                }

                $amount = (float) $member->paymentDetail->amount;

                $payload = [
                    'member_id'           => $member->id,
                    'amount'              => $amount,
                    'start_month_year'    => $validated['start_month_year'],
                    'subscription_period' => 1,
                ];

                // ✅ DISPATCH SATU PER SATU DI DALAM LOOP
                // Tambahkan delay agar ada jeda waktu pengerjaan
                BulkInvoiceJob::dispatch($payload)->delay(now()->addSeconds($delayInSeconds));

                $delayInSeconds += 2; // Tambah jeda 2 detik untuk member berikutnya
                $dispatchedCount++;
            }

            if ($dispatchedCount === 0) {
                return ResponseFormatter::error(null, 'Tidak ada member valid untuk diproses', 400);
            }

            ActivityLogController::logCreate([
                'action' => 'bulk_create_invoice',
                'status' => 'queued',
                'total'  => $dispatchedCount,
            ], 'invoices');

            return ResponseFormatter::success(
                ['total_member' => $dispatchedCount],
                'Bulk invoice sedang diproses',
                202
            );
        } catch (\Exception $e) {

            ActivityLogController::logCreateF([
                'action' => 'bulk_create_invoice',
                'error'  => $e->getMessage(),
            ], 'invoices');

            return ResponseFormatter::error(
                null,
                $e->getMessage(),
                400
            );
        } catch (\Throwable $th) {

            return ResponseFormatter::error(
                null,
                $th->getMessage(),
                500
            );
        }
    }


    public function manualPayment(Request $request)
    {
        DB::beginTransaction();

        try {

            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validated = $request->validate([
                'invoice_id'     => 'required|exists:invoices,id',
                'payment_method' => 'required|in:bank_transfer,cash',
            ]);


            $invoice = Invoice::with([
                'member',
                'connection.profile'
            ])
                ->where('id', $validated['invoice_id'])
                ->where('group_id', $user->group_id)
                ->firstOrFail();

            if ($invoice->status === 'paid') {

                return ResponseFormatter::error(
                    null,
                    'Invoice sudah dibayar',
                    400
                );
            }

            $invoice->update([
                'status'         => 'paid',
                'payment_method' => $validated['payment_method'],
                'paid_at'        => now(),
                'payer_id'       => $user->id,
            ]);

            DB::commit();
            $member = $invoice->member;
            try {
                if (!empty($member->phone_number) && str_starts_with($member->phone_number, '62')) {
                    $deviceId = $this->whatsapp->ensureDeviceByGroup($member->group_id);
                    $message  = $this->whatsapp->buildMessage([
                        'template'  => 'payment_paid',
                        'group_id'  => $member->group_id,
                        'variables' => [
                            'full_name'       => $member->fullname,
                            'no_invoice'      => $invoice->inv_number,
                            'total'           => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                            'pppoe_user'      => $member?->connection?->username,
                            'pppoe_profile'   => $member?->connection?->profile->name,
                            'period'          => $invoice->subscription_period,
                            'payment_gateway' => $invoice->payment_method === 'bank_transfer' ? 'Transfer Bank' : 'Cash',
                            'footer'          => 'PT. Anugerah Media Data Nusantara',
                        ],
                    ]);

                    $this->whatsapp->sendMessage($member->group_id, $deviceId, [
                        'phone'   => $member->phone_number,
                        'message' => $message,
                    ]);
                }
            } catch (\Exception $e) {
                // Jangan gagalkan payment kalau WhatsApp error
                Log::error('Failed to send WhatsApp payment_paid', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }
            /**
             * activity log
             */
            ActivityLogController::logCreate([
                'invoice_id' => $invoice->id,
                'member_id'  => $invoice->member_id,
                'amount'     => $invoice->amount,
                'method'     => $validated['payment_method'],
                'action'     => 'manual_payment',
                'status'     => 'success',
            ], 'invoices');

            return ResponseFormatter::success(
                $invoice->fresh(),
                'Pembayaran berhasil'
            );
        } catch (\Throwable $th) {

            DB::rollBack();

            ActivityLogController::logCreateF([
                'invoice_id' => $request->input('invoice_id'),
                'action'     => 'manual_payment',
                'error'      => $th->getMessage(),
            ], 'invoices');

            return ResponseFormatter::error(
                null,
                $th->getMessage(),
                500
            );
        }
    }

    public function paymentCancel(Request $request)
    {
        DB::beginTransaction();

        try {

            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validated = $request->validate([
                'invoice_id' => 'required|exists:invoices,id',
            ]);

            $invoice = Invoice::with([
                'member',
                'connection.profile'
            ])
                ->where('id', $validated['invoice_id'])
                ->where('group_id', $user->group_id)
                ->firstOrFail();

            if ($invoice->status !== 'paid') {

                return ResponseFormatter::error(
                    null,
                    'Invoice belum dibayar',
                    400
                );
            }

            $invoice->update([
                'status'         => 'unpaid',
                'payment_method' => null,
                'paid_at'        => null,
                'payer_id'       => null,
            ]);

            DB::commit();

            $member = $invoice->member;

            try {

                if (
                    !empty($member->phone_number) &&
                    str_starts_with($member->phone_number, '62')
                ) {

                    $deviceId = $this->whatsapp->ensureDeviceByGroup(
                        $member->group_id
                    );

                    $message = $this->whatsapp->buildMessage([
                        'template'  => 'payment_cancel',
                        'group_id'  => $member->group_id,
                        'variables' => [
                            'full_name'   => $member->fullname,
                            'no_invoice'  => $invoice->inv_number,
                            'total'       => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                            'invoice_date' => optional($invoice->created_at)->format('d-m-Y'),
                            'due_date'    => optional($invoice->due_date)->format('d-m-Y'),
                            'period'      => $invoice->subscription_period,
                            'footer'      => 'PT. Anugerah Media Data Nusantara',
                        ],
                    ]);

                    $this->whatsapp->sendMessage(
                        $member->group_id,
                        $deviceId,
                        [
                            'phone'   => $member->phone_number,
                            'message' => $message,
                        ]
                    );
                }
            } catch (\Exception $e) {

                Log::error('Failed to send WhatsApp payment_cancel', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            /**
             * activity log
             */
            ActivityLogController::logCreate([
                'invoice_id' => $invoice->id,
                'member_id'  => $invoice->member_id,
                'amount'     => $invoice->amount,
                'action'     => 'payment_cancel',
                'status'     => 'success',
            ], 'invoices');

            return ResponseFormatter::success(
                $invoice->fresh(),
                'Pembayaran berhasil dibatalkan'
            );
        } catch (\Throwable $th) {

            DB::rollBack();

            ActivityLogController::logCreateF([
                'invoice_id' => $request->input('invoice_id'),
                'action'     => 'payment_cancel',
                'error'      => $th->getMessage(),
            ], 'invoices');

            return ResponseFormatter::error(
                null,
                $th->getMessage(),
                500
            );
        }
    }

    public function destroy($id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $invoice = Invoice::findOrFail($id);
            $invoice->delete();

            return ResponseFormatter::success(null, 'Invoice berhasil dihapus');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
