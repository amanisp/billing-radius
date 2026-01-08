<?php

namespace App\Http\Controllers\Api;

use App\Events\ActivityLogged;
use App\Helpers\InvoiceHelper;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceHomepass;
use App\Models\Member;
use App\Models\PaymentDetail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FakturController extends Controller
{
    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }

    private function generateVirtualInvoices($lastInvoiceDate, $payment)
    {
        $now = Carbon::now()->startOfMonth();
        $invoices = [];

        $amount   = $payment->amount ?? 0;
        $ppnPct   = $payment->ppn ?? 0;
        $discount = $payment->discount ?? 0;

        $ppnNominal = $amount * ($ppnPct / 100);
        $totalPerMonth = ($amount + $ppnNominal) - $discount;

        /**
         * CASE 1: pelanggan baru / belum pernah bayar
         */
        if (empty($lastInvoiceDate)) {
            $invoices[] = [
                'billing_month' => $now->month,
                'billing_year'  => $now->year,
                'period'        => $now->translatedFormat('F Y'),

                'amount'        => $amount,
                'ppn_percent'   => $ppnPct,
                'ppn_amount'    => round($ppnNominal),
                'discount'      => $discount,
                'total'         => round($totalPerMonth),

                'status'        => 'UNPAID'
            ];
            return $invoices;
        }

        /**
         * CASE 2: sudah pernah bayar
         */
        $lastPaid = Carbon::parse($lastInvoiceDate)->startOfMonth();
        $cursor = $lastPaid->copy()->addMonth();

        while ($cursor <= $now) {
            $invoices[] = [
                'billing_month' => $cursor->month,
                'billing_year'  => $cursor->year,
                'period'        => $cursor->translatedFormat('F Y'),

                'amount'        => $amount,
                'ppn_percent'   => $ppnPct,
                'ppn_amount'    => round($ppnNominal),
                'discount'      => $discount,
                'total'         => round($totalPerMonth),

                'status'        => 'UNPAID'
            ];
            $cursor->addMonth();
        }

        return $invoices;
    }

    public function stats(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $now = Carbon::now();
            $currentMonth = $now->month;
            $currentYear  = $now->year;

            $members = Member::with(['paymentDetail'])
                ->where('group_id', $user->group_id)
                ->get();

            $stats = [
                'this_month' => [
                    'total_invoices' => 0,
                    'total_amount' => 0,
                    'paid_count' => 0,
                    'paid_amount' => 0,
                    'unpaid_count' => 0,
                    'unpaid_amount' => 0,
                ],
                'overdue' => [
                    'count' => 0,
                    'amount' => 0,
                ]
            ];

            foreach ($members as $member) {
                $payment = $member->paymentDetail;
                if (!$payment) continue;

                $total = ($payment->amount ?? 0) - ($payment->discount ?? 0);
                $total += isset($payment->ppn) ? ($total * $payment->ppn / 100) : 0;

                $total = round($total, 0); // 0 desimal untuk rupiah


                // ====================
                // 1️⃣ this_month
                // ====================
                $isPaid = false;
                if ($payment->last_invoice) {
                    $invoiceMonth = Carbon::parse($payment->last_invoice)->month;
                    $invoiceYear  = Carbon::parse($payment->last_invoice)->year;

                    if ($invoiceMonth == $currentMonth && $invoiceYear == $currentYear) {
                        $isPaid = true;
                    }
                }

                $stats['this_month']['total_invoices'] += 1;
                $stats['this_month']['total_amount'] += $total;

                if ($isPaid) {
                    $stats['this_month']['paid_count'] += 1;
                    $stats['this_month']['paid_amount'] += $total;
                } else {
                    $stats['this_month']['unpaid_count'] += 1;
                    $stats['this_month']['unpaid_amount'] += $total;
                }

                // ====================
                // 2️⃣ overdue
                // ====================
                if ($payment->last_invoice) {
                    $lastInvoice = Carbon::parse($payment->last_invoice);

                    // selisih bulan antara last_invoice dan bulan sebelumnya
                    $monthsDiff = ($currentYear - $lastInvoice->year) * 12 + ($currentMonth - 1 - $lastInvoice->month);

                    if ($monthsDiff > 0) {
                        $stats['overdue']['count'] += $monthsDiff;
                        $stats['overdue']['amount'] += $total * $monthsDiff; // akumulasi nominal tiap bulan
                    }
                }
                // NOTE: jika last_invoice null → jangan masuk overdue
            }

            return ResponseFormatter::success($stats, 'Stats pembayaran berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }


    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $now = Carbon::now();

            // Query awal
            $query = Member::with(['paymentDetail', 'connection.area']);

            // 🔒 Filter berdasarkan role
            if ($user->role === 'teknisi') {
                $assignedAreaIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id')
                    ->toArray();

                if (empty($assignedAreaIds)) {
                    $query->whereRaw('1 = 0'); // teknisi tanpa area assigned, hasil kosong
                } else {
                    $query->whereHas('connection', function ($q) use ($assignedAreaIds) {
                        $q->whereIn('area_id', $assignedAreaIds);
                    });
                }
            } else {
                $query->where('group_id', $user->group_id);
            }

            // Filter unpaid bulan ini
            $query->whereHas('paymentDetail', function (Builder $q) use ($now) {
                $q->where(function ($q2) use ($now) {
                    $q2->whereNull('last_invoice') // belum bayar sama sekali
                        ->orWhereRaw('(MONTH(last_invoice) != ? OR YEAR(last_invoice) != ?)', [$now->month, $now->year]);
                });
            });

            // 🔍 Search
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('connection', function ($q2) use ($search) {
                        $q2->where('username', 'like', "%{$search}%")
                            ->orWhere('internet_number', 'like', "%{$search}%")
                            ->orWhere('mac_address', 'like', "%{$search}%");
                    })
                        ->orWhere('fullname', 'like', "%{$search}%");
                });
            }

            // 🗺 Filter by area_id (optional, override teknisi filter jika ada)
            if ($request->has('area_id') && $request->area_id) {
                $query->whereHas('connection', function ($q) use ($request) {
                    $q->where('area_id', $request->area_id);
                });
            }

            // 🔒 Filter by isolir
            if ($request->filled('status')) { // cek kalau status ada dan tidak kosong
                $statusMap = [
                    'isolir' => 1,  // suspend
                    'active' => 0,  // aktif
                ];

                $statusValue = $statusMap[$request->status] ?? null;

                if (!is_null($statusValue)) {
                    $query->whereHas('connection', function ($q) use ($statusValue) {
                        $q->where('isolir', $statusValue);
                    });
                }
            }

            // 🔄 Sort
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = $request->get('per_page', 15);
            $connections = $query->paginate($perPage);

            return ResponseFormatter::success($connections, 'Data connections berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 200);
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
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }


    public function manualPayment(Request $request)
    {
        DB::beginTransaction();

        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $validated = $request->validate([
                'member_id'       => 'required|exists:members,id',
                'payment_method'  => 'required|in:bank_transfer,cash',
                'month'           => 'required|date_format:Y-m',
            ]);

            // Parse month (contoh: 2025-10)
            $startDate = \Carbon\Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
            $endDate   = $startDate->copy()->endOfMonth();

            $member = Member::with(['paymentDetail', 'connection.profile'])
                ->where('id', $validated['member_id'])
                ->where('billing', 1)
                ->firstOrFail();

            if ($member->group_id !== $user->group_id) {
                return response()->json(['message' => 'Member tidak ditemukan!'], 403);
            }

            $pd = $member->paymentDetail;

            $price    = $pd->amount;
            $vat      = $pd->ppn;
            $discount = $pd->discount;

            // Total 1 bulan
            $vatAmount  = ($price - $discount) * ($vat / 100);
            $totalAmount = ($price - $discount) + $vatAmount;


            $connection = $member->connection;
            $connectionId = $connection?->id;
            $areaId = $connection?->area_id ?? 1;

            // Generate invoice number
            $invNumber = InvoiceHelper::generateInvoiceNumber($areaId, 'H');

            // =============================
            // CREATE INVOICE (PAID MANUAL)
            // =============================
            $invoice = InvoiceHomepass::create([
                'connection_id'       => $connectionId,
                'payer_id'            => $user->id,
                'member_id'           => $member->id,
                'invoice_type'        => 'H',
                'start_date'          => $startDate->toDateString(),
                'due_date'            => $endDate->toDateString(),
                'subscription_period' => $startDate->translatedFormat('F Y'),
                'inv_number'          => $invNumber,
                'amount'              => $totalAmount,
                'payment_method'      => $validated['payment_method'],
                'status'              => 'paid',
                'paid_at'             => now(),
                'group_id'            => $member->group_id,
                'payment_url'         => 'https://bayar.amanisp.net.id'
            ]);

            // =============================
            // UPDATE LAST INVOICE (BULAN SAMA)
            // =============================
            PaymentDetail::where('id', $member->payment_detail_id)->update([
                'last_invoice' => $startDate->toDateString(),
            ]);

            DB::commit();

            ActivityLogged::dispatch('CREATE', null, $invoice);

            return ResponseFormatter::success(
                $invoice,
                'Invoice manual berhasil dibuat & dibayar',
                201
            );
        } catch (\Throwable $th) {
            DB::rollBack();
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

            $invoices = InvoiceHomepass::with('payer')->where('member_id', $id)
                ->where('group_id', $user->group_id)
                ->orderByDesc('created_at')
                ->paginate(5);

            if ($invoices->isEmpty()) {
                return ResponseFormatter::error(null, 'Invoice tidak ditemukan', 404);
            }

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
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
