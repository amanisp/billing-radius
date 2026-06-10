<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Controller;
use App\Models\CashFlow; // 👈 Gunakan model baru
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) {
            return $user;
        }

        $id = Auth::id();
        if ($id) {
            return User::find($id);
        }

        return null;
    }

    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // 👈 Gunakan scope expense() dari model CashFlow
            $query = CashFlow::query()->select(
                'id',
                'type',
                'description',
                'category',
                'amount',
                'transaction_date as expense_date',
                'user_id',
                'admin_id',
                'group_id',
                'created_at'
            )->with(['user:id,name']);

            /** ============================
             * ROLE & GROUP
             * ============================ */
            $query->where('group_id', $user->group_id);

            /** ============================
             * DEFAULT FILTER BULAN INI
             * ============================ */
            $rawMonth = $request->get('month', now()->format('m'));
            $year     = $request->get('year', now()->year);
            $month = sprintf('%02d', $rawMonth);

            $query->whereMonth('transaction_date', $month)
                ->whereYear('transaction_date', $year);

            /** ============================
             * SEARCH
             * ============================ */
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            }

            /** ============================
             * SORT
             * ============================ */
            $sortField = $request->get('sort_field', 'transaction_date');
            if ($sortField === 'expense_date') {
                $sortField = 'transaction_date'; // 👈 Map sort field
            }

            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            /** ============================
             * PAGINATION
             * ============================ */
            $perPage = $request->get('per_page', 5);
            $expenses = $query->paginate($perPage);

            return ResponseFormatter::success(
                $expenses,
                'Data pengeluaran bulan ini berhasil dimuat'
            );
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $request->validate([
                'description'  => 'required|string',
                'amount'       => 'required|numeric|min:0',
                'category'     => 'required|string',
                'expense_date' => 'required|date',
            ]);

            // 👈 Simpan ke CashFlow sebagai Pengeluaran
            $expense = CashFlow::create([
                'type'             => CashFlow::TYPE_OUT,
                'source_type'      => CashFlow::SOURCE_UMUM,
                'description'      => $request->description,
                'amount'           => $request->amount,
                'category'         => $request->category,
                'transaction_date' => $request->expense_date, // Map input ke kolom baru
                'user_id'          => Auth::id(),
                'group_id'         => $user->group_id ?? null,
            ]);

            ActivityLogController::logCreate(['action' => 'store', 'status' => 'success'], 'cash_flows');
            return ResponseFormatter::success($expense, 'Data pengeluaran berhasil dibuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500); // Ubah 200 jadi 500 jika error
        }
    }

    public function summary(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $month = $request->query('month', date('m'));
            $year  = $request->query('year', date('Y'));

            $currentRequestDate = Carbon::createFromDate($year, $month, 1);
            $lastMonthDate = $currentRequestDate->copy()->subMonth();


            // 1. Pemasukan (Dari Invoice Pelanggan)
            $incomeThisMonth = Invoice::whereNotNull('paid_at')
                ->where('group_id', $user->group_id)
                ->where('status', 'paid')
                ->whereMonth('paid_at', $month)
                ->whereYear('paid_at', $year)
                ->sum('amount');

            // 2. Pengeluaran (Ubah jadi CashFlow)
            $expenseThisMonth = CashFlow::expense()
                ->where('group_id', $user->group_id)
                ->whereMonth('transaction_date', $month)
                ->whereYear('transaction_date', $year)
                ->sum('amount');

            // 3. Net Cashflow
            $netCashflowThisMonth = $incomeThisMonth - $expenseThisMonth;

            // 4. Piutang (Invoice Pelanggan Bulan Ini Belum Dibayar)
            $piutangThisMonth = Invoice::where('group_id', $user->group_id)
                ->whereIn('status', ['unpaid'])

                ->whereMonth('start_date', $month) // Menggunakan bulan yang di-request
                ->whereYear('start_date', $year)
                ->sum('amount');

            // 5. Hutang (Invoice tipe 'H' Bulan Lalu yang Overdue/Belum lunas)
            $hutangThisMonth = Invoice::where('group_id', $user->group_id)
                ->where('status', 'unpaid')
                ->whereMonth('due_date', $lastMonthDate->month) // Acuan overdue biasanya dari due_date bulan lalu
                ->whereYear('due_date', $lastMonthDate->year)
                ->sum('amount');

            /** ============================
             * KAS ADMIN (BULAN INI)
             * ============================ */

            // 1. Ambil semua ID user yang berstatus 'mitra'
            $mitraUserIds = \App\Models\User::where('role', 'mitra')->pluck('id');

            // 2. Pemasukan Admin (Dari Invoice yang dibayar ke Admin/Sistem)
            $adminIncomeFromInvoice = Invoice::where('group_id', $user->group_id)
                ->where('status', 'paid')
                ->whereMonth('paid_at', $month)
                ->whereYear('paid_at', $year)
                ->where(function ($query) use ($mitraUserIds) {
                    // Pastikan invoice ini dieksekusi/diterima oleh role SELAIN mitra
                    $query->whereNotIn('payer_id', $mitraUserIds)
                        ->orWhereNull('payer_id');
                })
                ->sum('amount');

            // 3. Pemasukan Admin (Dari uang Setoran Mitra)
            $adminIncomeFromSetoran = CashFlow::where('group_id', $user->group_id)
                ->where('source_type', CashFlow::SOURCE_SETOR_ADMIN)
                ->whereMonth('transaction_date', $month)
                ->whereYear('transaction_date', $year)
                ->sum('amount');

            /** ============================
             * CHART DATA (6 BULAN TERAKHIR)
             * ============================ */
            $chartData = [];
            $monthsIndo = [
                1 => 'Jan',
                2 => 'Feb',
                3 => 'Mar',
                4 => 'Apr',
                5 => 'Mei',
                6 => 'Jun',
                7 => 'Jul',
                8 => 'Agt',
                9 => 'Sep',
                10 => 'Okt',
                11 => 'Nov',
                12 => 'Des'
            ];

            $currentDate = now()->startOfMonth();

            for ($i = 5; $i >= 0; $i--) {
                $targetDate = $currentDate->copy()->subMonths($i);
                $targetMonth = $targetDate->month;
                $targetYear = $targetDate->year;

                $inc = Invoice::whereNotNull('paid_at')
                    ->where('group_id', $user->group_id)
                    ->where('status', 'paid')
                    ->whereMonth('paid_at', $targetMonth)
                    ->whereYear('paid_at', $targetYear)
                    ->sum('amount');

                // Ubah jadi CashFlow
                $exp = CashFlow::expense()
                    ->where('group_id', $user->group_id)
                    ->whereMonth('transaction_date', $targetMonth)
                    ->whereYear('transaction_date', $targetYear)
                    ->sum('amount');

                $chartData[] = [
                    'month'   => $monthsIndo[$targetMonth],
                    'income'  => (int) $inc,
                    'expense' => (int) $exp,
                ];
            }

            /** ============================
             * CHART KATEGORI PENGELUARAN (DONUT CHART)
             * ============================ */
            // Ubah jadi CashFlow
            $expenseByCategory = CashFlow::expense()
                ->selectRaw('category, SUM(amount) as total')
                ->where('group_id', $user->group_id)
                ->whereMonth('transaction_date', $month)
                ->whereYear('transaction_date', $year)
                ->groupBy('category')
                ->get();

            $expenseChartLabels = $expenseByCategory->pluck('category')->map(function ($item) {
                return ucwords($item);
            });

            $expenseChartSeries = $expenseByCategory->pluck('total')->map(fn($val) => (int) $val);

            $data = [
                'month'        => (int) $month,
                'year'         => (int) $year,
                'income'       => (int) $incomeThisMonth,
                'expense'      => (int) $expenseThisMonth,
                'net_cashflow' => (int) $netCashflowThisMonth,
                'piutang'      => (int) $piutangThisMonth,
                'hutang'       => (int) $hutangThisMonth,
                'kas_admin'    => (int) $adminIncomeFromInvoice - $adminIncomeFromSetoran,
                'setor_admin'    => (int) $adminIncomeFromSetoran,
                'chart_data'   => $chartData,
                'expense_category_chart' => [
                    'labels' => $expenseChartLabels,
                    'series' => $expenseChartSeries,
                ]
            ];

            return ResponseFormatter::success($data, 'Data ringkasan pembukuan berhasil diambil');
        } catch (\Throwable $th) {
            return response()->json([
                'meta' => [
                    'code' => 500,
                    'status' => 'error',
                    'message' => $th->getMessage()
                ],
                'data' => null
            ], 500);
        }
    }

    public function setorAdmin(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Validasi data menggunakan Facade Validator
            $validator = Validator::make($request->all(), [
                'admin_id'         => 'required|exists:users,id',
                'amount'           => 'required|numeric|min:1',
                'note'             => 'nullable|string', // Frontend mengirim 'note'
                'transaction_date' => 'nullable|date'
            ]);

            // Jika validasi gagal, kembalikan error 422
            if ($validator->fails()) {
                return ResponseFormatter::error($validator->errors(), 'Validasi form gagal', 422);
            }

            // Buat (Insert) data baru ke database
            $data = CashFlow::create([
                'user_id'          => $user->id,          // Set ID user yang sedang login (kasir/mitra)
                'group_id'         => $user->group_id,    // Set Group ID dari user tersebut
                'admin_id'         => $request->admin_id, // Admin yang menerima setoran (dari form)
                'amount'           => $request->amount,
                'description'      => $request->note,     // Masukkan 'note' dari frontend ke 'description'
                'transaction_date' => $request->transaction_date ?? now(),
                'source_type'      => CashFlow::SOURCE_SETOR_ADMIN,
                'category'         => 'Setor Admin',
            ]);

            // Catat log aktivitas (opsional)
            ActivityLogController::logUpdate(['action' => 'create', 'status' => 'success'], 'cash_flows');

            return ResponseFormatter::success($data, 'Data setoran berhasil ditambahkan');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function adminLedger(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $now = \Carbon\Carbon::now();

            // 1. Mengambil daftar petugas terkait dalam satu grup
            $admins = User::whereIn('role', ['kasir', 'teknisi', 'admin'])
                ->where('group_id', $user->group_id)
                ->get();

            // 2. Mapping data untuk menghitung performa masing-masing petugas
            $data = $admins->map(function ($admin) use ($now, $user) {

                // 3. Mengambil data Invoice yang diterima oleh petugas di bulan ini
                $invoiceQuery = Invoice::where('payer_id', $admin->id)
                    ->where('group_id', $user->group_id)
                    ->where('status', 'paid') // Sesuaikan 'status' dan 'paid' dengan DB kamu
                    ->whereMonth('updated_at', $now->month)
                    ->whereYear('updated_at', $now->year);

                $totalReceived = (int) $invoiceQuery->sum('amount');
                $invoiceCount  = $invoiceQuery->count();

                // 4. Menghitung Total Setoran dari tabel CashFlow di bulan ini
                $totalDeposited = (int) CashFlow::income()
                    ->where('source_type', CashFlow::SOURCE_SETOR_ADMIN)
                    ->where('admin_id', $admin->id)
                    ->where('group_id', $user->group_id)
                    ->whereMonth('transaction_date', $now->month)
                    ->whereYear('transaction_date', $now->year)
                    ->sum('amount');

                // 5. Return data hasil rekap per petugas
                return [
                    'admin_id'        => $admin->id,
                    'admin_name'      => $admin->name,
                    'invoice_count'   => $invoiceCount,
                    'total_received'  => $totalReceived,
                    'total_deposited' => $totalDeposited,
                    'remaining'       => max(0, $totalReceived - $totalDeposited), // Sisa kas di tangan
                ];
            });

            return ResponseFormatter::success($data, 'Data rekap berhasil ditampilkan');
        } catch (\Throwable $th) {
            // Ubah code dari 200 menjadi 500 karena ini adalah tangkapan error sistem
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
