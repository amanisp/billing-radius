<?php

namespace App\Http\Controllers\Api;

use App\Events\ActivityLogged;
use App\Helpers\InvoiceHelper;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
use App\Jobs\BulkManualPaymentJob;
use App\Models\Invoice;
use App\Models\InvoiceHomepass;
use App\Models\Member;
use App\Models\PaymentDetail;
use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use App\Services\WhatsappNotificationService;
use App\Jobs\SendWhatsappMessageJob;
use App\Services\FonnteService;

class FakturController extends Controller
{

    protected $fonnte;

    public function __construct(FonnteService $fonnte)
    {
        $this->fonnte = $fonnte;
    }

    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }

    private function invoiceData($invoice)
    {
        return [
            'invoice' => $invoice,
            'moon' => Carbon::parse($invoice->due_date)->translatedFormat('F Y'),
            'priceBandwith' => 'Rp. ' . number_format($invoice->price, 0, ',', '.'),
        ];
    }


    public function single($inv_number)
    {
        try {
            // 1️⃣ Ambil invoice beserta relasi
            $invoice = InvoiceHomepass::where('inv_number', $inv_number)
                ->with([
                    'payer',
                    'member.connection.profile',
                    'member.paymentDetail'
                ])
                ->firstOrFail();  // Jika gagal, akan throw exception

            // 2️⃣ Ambil data pembayaran
            $amount   = $invoice->member->paymentDetail->amount ?? 0;
            $discount = $invoice->member->paymentDetail->discount ?? 0;
            $otherFee = 0; // Bisa ditambah jika ada biaya lain

            // 3️⃣ Hitung subtotal dan total
            $subtotal  = $amount - $discount + $otherFee;
            $total     = $subtotal;

            // 4️⃣ Bulan dari start_date
            $monthYear = Carbon::parse($invoice->start_date)->translatedFormat('F Y');

            // 5️⃣ Data untuk blade
            $data = array_merge(
                $this->invoiceData($invoice), // pastikan fungsi ini return array
                [
                    'mode'            => 'pdf', // bisa 'html' kalau mau preview di browser
                    'monthYear'       => $monthYear,
                    'amount'          => $amount,
                    'discount'        => $discount,
                    'subtotal'        => $subtotal,
                    'total'           => $total,
                    'nomor_pelanggan' => $invoice->member->connection->internet_number,
                ]
            );

            // 6️⃣ Render blade menjadi HTML
            $html = view('invoice.homepass', $data)->render();

            // 7️⃣ Generate PDF
            $mpdf = new Mpdf([
                'mode'    => 'utf-8',               // pastikan UTF-8 agar tidak blank
                'format'  => 'A4',
                'tempDir' => storage_path('app/mpdf'), // folder writable
            ]);

            $mpdf->WriteHTML($html);

            // 8️⃣ Output PDF sebagai string (bukan download langsung)
            $pdfContent = $mpdf->Output("", "S");  // "S" = return as string

            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Length', strlen($pdfContent))
                ->header('Content-Disposition', 'inline; filename="invoice-' . $inv_number . '.pdf"'); // 'inline' agar bisa diproses sebagai blob, bukan download otomatis

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Jika invoice tidak ditemukan, return error JSON (bukan PDF)
            return response()->json(['error' => 'Invoice not found'], 404);
        } catch (\Exception $e) {
            // Error umum (misalnya, mPDF gagal), return error JSON
            return response()->json(['error' => 'Failed to generate PDF'], 500);
        }
    }

    public function stats(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $now = Carbon::now();
            $currentMonth  = $now->month;
            $currentYear   = $now->year;
            $currentPeriod = $now->format('F Y'); // contoh: January 2026

            $stats = [
                'this_month' => [
                    'total_invoices' => 0,
                    'total_amount'   => 0,
                    'paid_count'     => 0,
                    'paid_amount'    => 0,
                    'unpaid_count'   => 0,
                    'unpaid_amount'  => 0,
                ],
                'overdue' => [
                    'count'  => 0,
                    'amount' => 0,
                ],
            ];

            $members = Member::with(['paymentDetail', 'invoices'])
                ->where('group_id', $user->group_id)
                ->get();

            foreach ($members as $member) {
                $payment = $member->paymentDetail;
                if (!$payment) continue;

                // ====================
                // Nominal tagihan bulanan
                // ====================
                $total = ($payment->amount ?? 0) - ($payment->discount ?? 0);
                if (!empty($payment->ppn)) {
                    $total += $total * $payment->ppn / 100;
                }
                $total = round($total, 0);

                // ====================
                // 1️⃣ THIS MONTH
                // ====================
                $stats['this_month']['total_invoices'] += 1;
                $stats['this_month']['total_amount']   += $total;

                $paidInvoiceThisMonth = $member->invoices
                    ->where('invoice_type', 'H')
                    ->where('status', 'paid')
                    ->where('subscription_period', $currentPeriod)
                    ->first();

                if ($paidInvoiceThisMonth) {
                    $stats['this_month']['paid_count']  += 1;
                    $stats['this_month']['paid_amount'] += $total;
                } else {
                    $stats['this_month']['unpaid_count']  += 1;
                    $stats['this_month']['unpaid_amount'] += $total;
                }

                // ====================
                // 2️⃣ OVERDUE
                // ====================
                $lastPaidInvoice = $member->invoices
                    ->where('invoice_type', 'H')
                    ->where('status', 'paid')
                    ->whereNotNull('paid_at')
                    ->sortByDesc('paid_at')
                    ->first();

                if ($lastPaidInvoice) {
                    $lastPaidDate = Carbon::parse($lastPaidInvoice->paid_at);

                    // hitung selisih bulan (bulan berjalan tidak dihitung)
                    $monthsDiff =
                        ($currentYear - $lastPaidDate->year) * 12 +
                        ($currentMonth - 1 - $lastPaidDate->month);

                    if ($monthsDiff > 0) {
                        $stats['overdue']['count']  += $monthsDiff;
                        $stats['overdue']['amount'] += $total * $monthsDiff;
                    }
                }
            }


            return ResponseFormatter::success(
                $stats,
                'Stats pembayaran berhasil dimuat'
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
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $query = Member::query()
                ->with([
                    'paymentDetail',
                    'connection.area',
                    'invoices' => function ($q) {
                        $q->where('status', 'paid');
                    }
                ]);

            // ========================
            // Role filter
            // ========================
            if (in_array($user->role, ['teknisi', 'kasir'])) {

                $areaIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id');

                if ($areaIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas('connection', function ($q) use ($areaIds) {
                        $q->whereIn('area_id', $areaIds);
                    });
                }
            } else {
                $query->where('members.group_id', $user->group_id);
            }

            // ========================
            // Target month
            // ========================
            $month = $request->get('month', now()->month);
            $year  = $request->get('year', now()->year);

            $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
            $endOfMonth   = Carbon::create($year, $month, 1)->endOfMonth();

            // ========================
            // Billing status filter
            // ========================
            if ($request->get('billing_status') === 'paid') {
                $query->whereHas('invoices', function ($q) use ($startOfMonth, $endOfMonth) {
                    $q->where('status', 'paid')
                        ->whereDate('start_date', '<=', $endOfMonth)
                        ->whereDate('due_date', '>=', $startOfMonth);
                });
            }

            if ($request->get('billing_status') === 'unpaid') {
                $query->whereDoesntHave('invoices', function ($q) use ($startOfMonth, $endOfMonth) {
                    $q->where('status', 'paid')
                        ->whereDate('start_date', '<=', $endOfMonth)
                        ->whereDate('due_date', '>=', $startOfMonth);
                });
            }

            // ========================
            // Search (OPTIMAL VERSION)
            // ========================
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('members.fullname', 'like', "%{$search}%")
                        ->orWhereHas('connection', function ($q2) use ($search) {
                            $q2->where('username', 'like', "%{$search}%");
                        });
                });
            }

            // ========================
            // Area filter
            // ========================
            if ($request->filled('area_id')) {
                $query->whereHas('connection', function ($q) use ($request) {
                    $q->where('area_id', $request->area_id);
                });
            }

            // ========================
            // Isolir filter
            // ========================
            if ($request->filled('status')) {
                $statusMap = ['isolir' => 1, 'active' => 0];

                if (isset($statusMap[$request->status])) {
                    $query->whereHas('connection', function ($q) use ($statusMap, $request) {
                        $q->where('isolir', $statusMap[$request->status]);
                    });
                }
            }

            // ========================
            // Sort & paginate
            // ========================
            $query->orderBy(
                $request->get('sort_field', 'members.created_at'),
                $request->get('sort_direction', 'desc')
            );

            $data = $query->paginate($request->get('per_page', 15));

            return ResponseFormatter::success($data, 'Data berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
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
            $query = InvoiceHomepass::with(['payer', 'member'])
                ->where('group_id', $user->group_id);

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


    public function fakturDetail($memberId)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $member = Member::with(['paymentDetail', 'connection.area', 'connection.optical'])
                ->where('id', $memberId)
                ->where('group_id', $user->group_id)
                ->firstOrFail();

            $payment = $member->paymentDetail;
            if (!$payment) {
                ActivityLogController::logCreateF([
                    'member_id' => $memberId,
                    'action' => 'view_faktur_detail',
                    'error' => 'Payment detail tidak ditemukan'
                ], 'invoices');
                return ResponseFormatter::error(null, 'Payment detail tidak ditemukan', 404);
            }

            /* ===============================
         * HITUNG TAGIHAN DASAR
         * =============================== */
            $baseAmount = $payment->amount ?? 0;
            $discount   = $payment->discount ?? 0;
            $ppn        = $payment->ppn ?? 0;

            $totalAfterTax = ($baseAmount - $discount) * (1 + $ppn / 100);

            /* ===============================
         * LAST INVOICE = BULAN TAGIHAN
         * =============================== */
            $lastInvoice = $payment->last_invoice
                ? Carbon::parse($payment->last_invoice)->startOfMonth()
                : now()->startOfMonth();

            $virtualInvoices = [];

            /* ===============================
         * 1️⃣ 3 BULAN KE BELAKANG
         * =============================== */
            for ($i = 3; $i > 0; $i--) {
                $date     = $lastInvoice->copy()->subMonths($i);
                $monthStr = $date->format('Y-m');

                $invoice = InvoiceHomepass::where('member_id', $member->id)
                    ->whereYear('start_date', $date->year)
                    ->whereMonth('start_date', $date->month)
                    ->first();

                $isPaid = (bool) ($invoice?->paid_at);

                $virtualInvoices[] = [
                    'month'   => $monthStr,
                    'total'   => $isPaid ? $invoice->amount : round($totalAfterTax),
                    'status'  => $isPaid ? 'paid' : 'unpaid',
                    'paid_at' => $isPaid
                        ? Carbon::parse($invoice->paid_at)->toDateString()
                        : null,
                ];
            }

            /* ===============================
         * 2️⃣ BULAN INI + KE DEPAN (TOTAL 12)
         * =============================== */
            $monthsForward = 12 - count($virtualInvoices);

            for ($i = 0; $i < $monthsForward; $i++) {
                $date     = $lastInvoice->copy()->addMonths($i);
                $monthStr = $date->format('Y-m');

                $invoice = InvoiceHomepass::where('member_id', $member->id)
                    ->whereYear('start_date', $date->year)
                    ->whereMonth('start_date', $date->month)
                    ->first();

                $isPaid = (bool) ($invoice?->paid_at);

                $virtualInvoices[] = [
                    'month'   => $monthStr,
                    'total'   => $isPaid ? $invoice->amount : round($totalAfterTax),
                    'status'  => $isPaid ? 'paid' : 'unpaid',
                    'paid_at' => $isPaid
                        ? Carbon::parse($invoice->paid_at)->toDateString()
                        : null,
                ];
            }

            ActivityLogController::logCreate([
                'member_id' => $memberId,
                'action' => 'view_faktur_detail',
                'total_invoices' => count($virtualInvoices),
                'status' => 'success'
            ], 'invoices');

            /* ===============================
         * RESPONSE FINAL
         * =============================== */
            return ResponseFormatter::success([
                'member'        => $member,
                'total_invoice' => count($virtualInvoices),
                'grand_total'   => collect($virtualInvoices)->sum('total'),
                'invoices'      => $virtualInvoices,
            ], 'Detail faktur berhasil dimuat');
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF([
                'member_id' => $memberId ?? null,
                'action' => 'view_faktur_detail',
                'error' => $th->getMessage()
            ], 'invoices');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function bulkManualPayment(Request $request)
    {
        $user = $this->getAuthUser();

        $validated = $request->validate([
            'list_id' => 'required|array|min:1',
            'payment_method' => 'required|in:bank_transfer,cash',
            'month' => 'required|date_format:Y-m',
        ]);

        BulkManualPaymentJob::dispatch(
            $validated['list_id'],
            $validated['payment_method'],
            $validated['month'],
            $user->id
        );

        return ResponseFormatter::success(
            null,
            'Bulk payment sedang diproses di background',
            202
        );
    }


    public function manualPayment(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Validasi input
            $validated = $request->validate([
                'member_id' => 'required|exists:members,id',
                'payment_method' => 'required|in:bank_transfer,cash',
                'month' => 'required|date_format:Y-m',
            ]);

            // Parse month (contoh: 2025-10)
            $startDate = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            // Ambil member
            $member = Member::with('paymentDetail', 'connection.profile')
                ->where('id', $validated['member_id'])
                ->where('billing', 1)
                ->firstOrFail();

            // Validasi group
            if ($member->group_id != $user->group_id) {
                ActivityLogController::logCreateF([
                    'member_id' => $validated['member_id'],
                    'action' => 'manual_payment',
                    'error' => 'Member tidak ditemukan atau tidak sesuai group'
                ], 'invoices');

                return response()->json(['message' => 'Member tidak ditemukan!'], 403);
            }

            // Ambil payment detail
            $pd = $member->paymentDetail;
            $price = $pd->amount;
            $vat = $pd->ppn;
            $discount = $pd->discount;

            // Hitung total (1 bulan)
            $totalAmount = $price - $discount;

            $connection = $member->connection;
            $connectionId = $connection?->id;
            $areaId = $connection?->area_id ?? 1;

            // Generate invoice number
            $invNumber = InvoiceHelper::generateInvoiceNumber($areaId, 'H');

            // CREATE INVOICE (PAID - MANUAL)
            $invoice = InvoiceHomepass::create([
                'connection_id' => $connectionId,
                'payer_id' => $user->id,
                'member_id' => $member->id,
                'invoice_type' => 'H',
                'start_date' => $startDate->toDateString(),
                'due_date' => $endDate->toDateString(),
                'subscription_period' => $startDate->translatedFormat('F Y'),
                'inv_number' => $invNumber,
                'amount' => $totalAmount,
                'payment_method' => $validated['payment_method'],
                'status' => 'paid',
                'paid_at' => now(),
                'group_id' => $member->group_id,
                'payment_url' => 'https://bayar.amanisp.net.id', // UPDATE
            ]);

            // UPDATE LAST INVOICE (BULAN SAMA)
            PaymentDetail::where('id', $member->payment_detail_id)->update([
                'last_invoice' => $startDate->toDateString(),
            ]);

            DB::commit();

            // ========== KIRIM WHATSAPP NOTIFIKASI (BARU) ==========
            try {
                Log::info('Dispatching WhatsApp for manual payment', [
                    'invoice_id' => $invoice->id,
                    'member_id' => $member->id,
                    'phone' => $member->phone_number,
                ]);

                // Dispatch job untuk kirim WhatsApp
                if (!empty($member->phone_number) && str_starts_with($member->phone_number, '62')) {
                    $this->fonnte->sendText(
                        $user->group_id,
                        $member->phone_number,
                        [
                            'template' => 'payment_paid',
                            'variables' => [
                                'full_name'        => $member->fullname,
                                'no_invoice'       => $invNumber,
                                'total' => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                                'pppoe_user'       => $connection?->username,
                                'pppoe_profile'    => $connection?->profile->name,
                                'period'           => $invoice->subscription_period,
                                'payment_gateway'  => $invoice->payment_method === 'back_transfer' ? 'Transfer Bank' : 'Cash',
                                'footer'           => 'PT. Anugerah Media Data Nusantara'
                            ]
                        ]
                    );
                }

                Log::info('WhatsApp job dispatched successfully', [
                    'invoice_id' => $invoice->id,
                ]);
            } catch (\Exception $e) {
                // Jangan gagalkan payment kalau WhatsApp error
                Log::error('Failed to dispatch WhatsApp job', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
            // ========== END WHATSAPP ==========

            // Log sukses
            ActivityLogController::logCreate([
                'member_id' => $validated['member_id'],
                'invoice_id' => $invoice->id,
                'amount' => $totalAmount,
                'method' => $validated['payment_method'],
                'month' => $validated['month'],
                'action' => 'manual_payment',
                'status' => 'success'
            ], 'invoices');

            return ResponseFormatter::success($invoice, 'Invoice manual berhasil dibuat & dibayar', 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            ActivityLogController::logCreateF([
                'member_id' => $request->input('member_id') ?? null,
                'action' => 'manual_payment',
                'error' => $th->getMessage()
            ], 'invoices');

            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }



    public function invoiceByMemberId(Request $request, $id)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $invoices = InvoiceHomepass::with('payer')
                ->where('member_id', $id)
                ->where('group_id', $user->group_id)
                ->orderByDesc('created_at')
                ->paginate(5);

            ActivityLogController::logCreate([
                'member_id' => $id,
                'action' => 'view_member_invoices',
                'total_invoices' => $invoices->total(),
                'status' => 'success'
            ], 'invoices');

            // Jangan return error meskipun kosong, return data pagination saja
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
                'member_id' => $id ?? null,
                'action' => 'view_member_invoices',
                'error' => $th->getMessage()
            ], 'invoices');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function paymentCancel(Request $request, $id)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Cari invoice
            $invoice = InvoiceHomepass::where('id', $id)
                ->where('group_id', $user->group_id)
                ->with('member')
                ->first();

            if (!$invoice) {
                return ResponseFormatter::error(null, 'Invoice tidak ditemukan', 404);
            }

            // if (!empty($invoice->member->phone_number) && str_starts_with($invoice->member->phone_number, '62')) {
            //     $this->fonnte->sendText(
            //         $user->group_id,
            //         $invoice->member->phone_number,
            //         [
            //             'template' => 'payment_cancel',
            //             'variables' => [
            //                 'full_name'     => $invoice->member->fullname,
            //                 'no_invoice'    => $invoice->inv_number,
            //                 'total' => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
            //                 'invoice_date' => $invoice->paid_at ? $invoice->paid_at->format('Y-m-d') : null,
            //                 'due_date'     => $invoice->due_date ? $invoice->due_date->format('Y-m-d') : null,
            //                 'period'      => $invoice->subscription_period,
            //             ]
            //         ]
            //     );
            // }

            // Hapus invoice
            $invoice->delete();

            return ResponseFormatter::success(null, 'Invoice berhasil dihapus');
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF([
                'member_id' => $invoice->member_id ?? null,
                'action' => 'cancel_invoice',
                'error' => $th->getMessage()
            ], 'invoices');

            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
