<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
use App\Jobs\BulkInvoiceJob;
use App\Jobs\BulkManualPaymentJob;
use App\Models\GlobalSettings;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\User;
use App\Services\ExpoNotificationService;
use App\Services\InvoiceService;
use App\Services\WhatsappCoreService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;

class InvoiceController extends Controller
{

    protected $whatsapp;
    protected $invoiceService;

    public function __construct(InvoiceService $invoiceService, WhatsappCoreService $whatsapp)
    {
        $this->invoiceService = $invoiceService;
        $this->whatsapp = $whatsapp;
    }

    private function formatPeriod(?string $date): string
    {
        if (!$date) {
            return '-'; // Fallback jika tanggal kosong
        }

        // Set locale ke bahasa Indonesia
        \Carbon\Carbon::setLocale('id');

        // F = Nama Bulan penuh, Y = Tahun 4 digit
        return \Carbon\Carbon::parse($date)->translatedFormat('F Y');
    }

    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }

    public function generatePdf(Request $request, $inv_number)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // 1. Base Query (Sama dengan index, ambil relasi yang dibutuhkan template HTML)
            $query = Invoice::with(['member.connection.profile', 'member.paymentDetail'])
                ->where('group_id', $user->group_id)
                ->where('inv_number', $inv_number);

            // 2. 🛡️ Filter area khusus untuk teknisi / kasir
            if (in_array($user->role, ['teknisi', 'kasir'])) {
                $areaIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id');

                if ($areaIds->isEmpty()) {
                    return response()->json(['message' => 'Anda tidak memiliki akses area'], 403);
                }

                $query->whereHas('connection', function ($q) use ($areaIds) {
                    $q->whereIn('area_id', $areaIds);
                });
            }

            // 3. Eksekusi query (Gunakan firstOrFail agar jika tidak ada/bukan areanya, langsung error 404)
            $invoice = $query->firstOrFail();

            // 4. Siapkan variabel yang dibutuhkan oleh template HTML kamu
            // Asumsi nomor internet diambil dari connection
            $nomor_pelanggan = $invoice->member->connection->internet_number ?? $invoice->member->connection->username ?? '-';

            // Format bulan bahasa indonesia (Contoh: "Mei 2026")
            $moon = Carbon::parse($invoice->start_date)->locale('id')->translatedFormat('F Y');

            // Kalkulasi nominal
            $amount = $invoice->amount ?? 0;
            $discount = $invoice->discount ?? 0;
            $total = $amount - $discount;

            // 5. Render view HTML menjadi PDF
            // Asumsi template file blade kamu ada di: resources/views/pdf/invoice.blade.php
            $pdf = Pdf::loadView('invoice.homepass', compact(
                'invoice',
                'nomor_pelanggan',
                'moon',
                'amount',
                'discount',
                'total'
            ));

            // Set ukuran kertas (opsional)
            $pdf->setPaper('A4', 'portrait');

            // 6. Return response stream (akan terbuka langsung di browser)
            // Gunakan ->download() jika ingin file otomatis terunduh
            return $pdf->stream('Invoice-' . $invoice->inv_number . '.pdf');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Invoice tidak ditemukan atau Anda tidak memiliki akses ke invoice ini.'
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat membuat PDF: ' . $th->getMessage()
            ], 500);
        }
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

            // Mendapatkan parameter waktu bulan ini dan bulan kemarin
            $now = now();
            $currentMonth = $now->month;
            $currentYear = $now->year;

            $lastMonthDate = $now->copy()->subMonth();
            $lastMonth = $lastMonthDate->month;
            $lastYear = $lastMonthDate->year;

            /**
             * -------------------------------------------------------------
             * 1. Ambil Data Area Jika User adalah Teknisi atau Kasir
             * -------------------------------------------------------------
             */
            $areaIds = collect();
            if (in_array($user->role, ['teknisi', 'kasir'])) {
                $areaIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id');

                // Jika teknisi/kasir tidak punya area, langsung return 0 semua
                if ($areaIds->isEmpty()) {
                    return ResponseFormatter::success([
                        'paid' => ['count' => 0, 'amount' => 0],
                        'unpaid' => ['count' => 0, 'amount' => 0],
                        'overdue' => ['count' => 0, 'amount' => 0],
                        'revenue' => 0,
                    ], 'Tidak ada area yang ditugaskan');
                }
            }

            /**
             * -------------------------------------------------------------
             * 2. Buat Base Query Terpisah untuk Kelompok Data
             * -------------------------------------------------------------
             */
            $paidBaseQuery = Invoice::query()->where('group_id', $user->group_id);
            $unpaidBaseQuery = Invoice::query()->where('group_id', $user->group_id);

            /**
             * -------------------------------------------------------------
             * 3. Terapkan Filter Hak Akses
             * -------------------------------------------------------------
             */

            // --- A. FILTER UNTUK METRIK PAID & REVENUE ---
            // Jika role BUKAN admin atau mitra, maka hanya hitung yang payer_id nya sama dengan user yg login
            if (!in_array($user->role, ['admin', 'mitra'])) {
                $paidBaseQuery->where('payer_id', $user->id);
            }

            // --- B. FILTER UNTUK METRIK UNPAID & OVERDUE ---
            if (in_array($user->role, ['teknisi', 'kasir'])) {
                // Teknisi & Kasir: melihat tagihan belum dibayar berdasarkan Area tugasnya
                $unpaidBaseQuery->whereHas('connection', function ($q) use ($areaIds) {
                    $q->whereIn('area_id', $areaIds);
                });
            } elseif (!in_array($user->role, ['admin', 'mitra'])) {
                // Role Lainnya (misal Pelanggan): hanya melihat tagihan belum dibayar miliknya sendiri
                $unpaidBaseQuery->where('payer_id', $user->id);
            } 
            // NOTE: Jika role adalah 'admin' atau 'mitra', query tidak difilter (melihat global grup)


            /**
             * -------------------------------------------------------------
             * 4. Eksekusi Perhitungan Metrik
             * -------------------------------------------------------------
             */

            /** METRIK PAID - Bulan Ini (Menggunakan paidBaseQuery) */
            $paidQuery = (clone $paidBaseQuery)
                ->where('status', 'paid')
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear);

            $paidCount = (clone $paidQuery)->count();
            $paidAmount = (clone $paidQuery)->sum('amount');

            /** METRIK UNPAID - Bulan Ini (Menggunakan unpaidBaseQuery) */
            $unpaidQuery = (clone $unpaidBaseQuery)
                ->where('status', 'unpaid')
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear);

            $unpaidCount = (clone $unpaidQuery)->count();
            $unpaidAmount = (clone $unpaidQuery)->sum('amount');

            /** METRIK OVERDUE - Bulan Kemarin Saja (Menggunakan unpaidBaseQuery) */
            $overdueQuery = (clone $unpaidBaseQuery)
                ->where('status', 'unpaid')
                ->whereMonth('created_at', $lastMonth)
                ->whereYear('created_at', $lastYear);

            $overdueCount = (clone $overdueQuery)->count();
            $overdueAmount = (clone $overdueQuery)->sum('amount');

            /** METRIK TOTAL REVENUE - Tagihan Bulan Ini Saja (Menggunakan paidBaseQuery) */
            $totalRevenue = (clone $paidBaseQuery)
                ->where('invoice_type', 'H')
                ->where('status', 'paid')
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->sum('amount');

            /**
             * -------------------------------------------------------------
             * 5. Response Output
             * -------------------------------------------------------------
             */
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

            // 1. Base Query
            $query = Invoice::with(['member.paymentDetail', 'connection.area', 'connection.profile'])
                ->where('group_id', $user->group_id)
                ->where('status', 'unpaid');

            // 2. 🛡️ Filter area khusus untuk teknisi / kasir
            if (in_array($user->role, ['teknisi', 'kasir'])) {
                $areaIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id');

                if ($areaIds->isEmpty()) {
                    // Trik: Paksa query agar tidak mengembalikan data apa pun 
                    // tapi tetap mempertahankan format response Paginasi (agar frontend tidak error)
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas('connection', function ($q) use ($areaIds) {
                        $q->whereIn('area_id', $areaIds);
                    });
                }
            }

            // 3. 🔍 Search
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('inv_number', 'like', "%{$search}%")
                        ->orWhereHas('member', function ($m) use ($search) {
                            $m->where('fullname', 'like', "%{$search}%");
                        });
                });
            }

            // 4. 📅 Filter bulan & tahun
            if ($month = $request->get('month')) {
                $query->whereMonth('start_date', $month);
            }

            if ($year = $request->get('year')) {
                $query->whereYear('start_date', $year);
            }

            // 5. 📍 Filter area spesifik dari request (frontend)
            if ($areaId = $request->get('area_id')) {
                $query->whereHas('connection', function ($q) use ($areaId) {
                    $q->where('area_id', $areaId);
                });
            }

            // 6. 🔄 Sort
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // 7. 📄 Pagination
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
                ->where('group_id', $user->group_id)
                ->where('status', 'paid');

            // ========================
            // 🛡️ Role-based Filter (Hak Akses)
            // ========================
            // Jika role BUKAN admin atau mitra, batasi hanya melihat datanya sendiri
            if (!in_array($user->role, ['admin', 'mitra'])) {
                $query->where('payer_id', $user->id);
            }

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
            // Payer filter (Hanya berlaku efektif jika admin/mitra, atau jika user biasa mencari namanya sendiri)
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

    public function manualPayment(Request $request, ExpoNotificationService $expoService) // Inject service di parameter
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

            // ==========================================
            // 📲 1. WHATSAPP NOTIFICATION
            // ==========================================
            try {
                if (!empty($member->phone_number) && str_starts_with($member->phone_number, '62')) {
                    $deviceId = $this->whatsapp->ensureDeviceByGroup($member->group_id);
                    $message  = $this->whatsapp->buildMessage([
                        // ... (kode WA kamu biarkan utuh seperti sebelumnya)
                        'template'  => 'payment_paid',
                        'group_id'  => $member->group_id,
                        'variables' => [
                            'full_name'       => $member->fullname,
                            'no_invoice'      => $invoice->inv_number,
                            'total'           => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                            'pppoe_user'      => $member?->connection?->username,
                            'pppoe_profile'   => $member?->connection?->profile->name,
                            'period'          => $this->formatPeriod($invoice->start_date),
                            'payment_gateway' => $invoice->payment_method === 'bank_transfer' ? 'Transfer Bank' : 'Cash',
                            'footer'          => 'PT. Anugerah Media Data Nusantara',
                        ],
                    ]);

                    $this->whatsapp->sendMessage($member->group_id, $deviceId, [
                        'phone'   => $member->phone_number,
                        'message' => $message,
                    ]);
                }
            } catch (\Throwable $e) {
                // UBAH \Exception menjadi \Throwable
                // Ini akan mengurung SEMUA jenis error (termasuk 503 dari service WA).
                // Karena error sudah "ditangkap" di sini, kode di bawahnya akan tetap dilanjutkan.
                Log::error('Failed to send WhatsApp payment_paid', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            // ==========================================
            // 🔔 2. EXPO PUSH NOTIFICATION (VIA SERVICE)
            // ==========================================
            // Panggil fungsi service, kirimkan parameter yang dibutuhkan
            try {
                $expoService->sendPaymentSuccessNotification(
                    $user->group_id,
                    $invoice,
                    $member,
                    $validated['payment_method']
                );
            } catch (\Throwable $e) {
                // Bungkus juga dengan try-catch agar jika Expo yang error,
                // Activity Log di bawahnya tetap tersimpan.
                Log::error('Failed to send Expo notification', [
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            // ==========================================
            // 📝 3. ACTIVITY LOG
            // ==========================================
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

    public function manualPaymentBulk(Request $request)
    {
        $user = $this->getAuthUser();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        // 1. Validasi request
        $validated = $request->validate([
            'invoice_ids'   => 'required|array',
            'invoice_ids.*' => 'exists:invoices,id',
            'payment_method' => 'required|string',
        ]);

        $paymentMethodInput = strtolower($validated['payment_method']);
        $paymentMethod = in_array($paymentMethodInput, ['transfer', 'bank_transfer'])
            ? 'bank_transfer'
            : 'cash';

        try {
            // 2. Tembak antrean ke Job (Background Process)
            BulkManualPaymentJob::dispatch(
                $validated['invoice_ids'],
                $paymentMethod,
                $user->id,
                $user->group_id
            );

            // 3. Return response secepatnya tanpa harus menunggu proses update selesai
            return ResponseFormatter::success(
                null,
                count($validated['invoice_ids']) . ' tagihan sedang diproses di latar belakang'
            );
        } catch (\Throwable $th) {

            ActivityLogController::logCreateF([
                'action' => 'manual_payment_bulk_dispatch',
                'error'  => $th->getMessage(),
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
                            'due_date'    => $invoice->due_date,
                            'period'      =>  $this->formatPeriod($invoice->start_date),
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
