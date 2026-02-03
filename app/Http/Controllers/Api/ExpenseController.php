<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Controller;
use App\Models\AdminDeposit;
use App\Models\Expense;
use App\Models\InvoiceHomepass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

            $query = Expense::select(
                'id',
                'description',
                'category',
                'amount',
                'expense_date',
                'user_id',
                'group_id',
                'created_at'
            )
                ->with(['user:id,name']);

            /** ============================
             * ROLE & GROUP
             * ============================ */
            $query->where('group_id', $user->group_id);

            /** ============================
             * DEFAULT FILTER BULAN INI
             * ============================ */
            $month = $request->get('month', now()->month);
            $year  = $request->get('year', now()->year);

            $query->whereMonth('expense_date', $month)
                ->whereYear('expense_date', $year);

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
            $sortField = $request->get('sort_field', 'expense_date');
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

            $expense = Expense::create([
                'description'  => $request->description,
                'amount'       => $request->amount,
                'category'     => $request->category,
                'expense_date' => $request->expense_date,
                'user_id'      => Auth::id(),
                'group_id'     => Auth::user()->group_id ?? null,
            ]);

            ActivityLogController::logCreate(['action' => 'store', 'status' => 'success'], 'expenses');
            return ResponseFormatter::success($expense, 'Data pengeluaran berhasil dibuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }

    public function summary()
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            $now = Carbon::now();

            /** ============================
             * TOTAL BULAN INI
             * ============================ */
            $incomeThisMonth = InvoiceHomepass::whereNotNull('paid_at')
                ->where('status', 'paid')
                ->whereMonth('paid_at', $now->month)
                ->whereYear('paid_at', $now->year)
                ->sum('amount');

            $expenseThisMonth = Expense::whereMonth('expense_date', $now->month)
                ->whereYear('expense_date', $now->year)
                ->sum('amount');

            /** ============================
             * RANGE: 1 BULAN KE BELAKANG + 10 BULAN KE DEPAN
             * ============================ */
            $startDate = $now->copy()->subMonth()->startOfMonth();
            $endDate   = $now->copy()->addMonths(10)->endOfMonth();

            /** ============================
             * PEMASUKAN (REAL DATA SAJA)
             * ============================ */
            $incomeData = InvoiceHomepass::select(
                DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as month"),
                DB::raw("SUM(amount) as total")
            )
                ->where('status', 'paid')
                ->whereNotNull('paid_at')
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->groupBy('month')
                ->pluck('total', 'month');

            /** ============================
             * PENGELUARAN (REAL DATA SAJA)
             * ============================ */
            $expenseData = Expense::select(
                DB::raw("DATE_FORMAT(expense_date, '%Y-%m') as month"),
                DB::raw("SUM(amount) as total")
            )
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->groupBy('month')
                ->pluck('total', 'month');

            /** ============================
             * BUILD 12 BULAN (DEFAULT 0)
             * ============================ */
            $last12Months = [];

            for ($i = -1; $i <= 4; $i++) {
                $monthKey = $now->copy()->addMonths($i)->format('Y-m');

                $last12Months[] = [
                    'month'   => $monthKey,
                    'income'  => (int) ($incomeData[$monthKey] ?? 0),
                    'expense' => (int) ($expenseData[$monthKey] ?? 0),
                ];
            }

            /** ============================
             * RESPONSE
             * ============================ */
            $data = [
                'month_now' => [
                    'income'  => (int) $incomeThisMonth,
                    'expense' => (int) $expenseThisMonth,
                    'balance' => (int) ($incomeThisMonth - $expenseThisMonth),
                ],
                'last_12_months' => $last12Months,
            ];

            return ResponseFormatter::success($data, 'Data ringkasan pembukuan berhasil diambil');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function setor(Request $request)
    {
        try {

            $user = $this->getAuthUser();

            if (!$user || $user->role !== 'mitra') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $request->validate([
                'admin_id' => 'required|exists:users,id',
                'amount' => 'required|numeric|min:1'
            ]);

            $data =  AdminDeposit::create([
                'admin_id' => $request->admin_id,
                'created_by' => $user->id,
                'amount' => $request->amount,
                'note' => $request->note
            ]);


            return ResponseFormatter::success($data, 'Data setor berhasil dibuat');
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

            $now = Carbon::now();

            $admins = User::whereIn('role', ['mitra', 'kasir', 'teknisi', 'admin'])
                ->where('group_id', $user->group_id)
                ->get();

            $data = $admins->map(function ($admin) use ($now) {

                // 🔹 Invoice dibayar bulan ini
                $invoiceQuery = InvoiceHomepass::where('payer_id', $admin->id)
                    ->whereNotNull('paid_at')
                    ->whereMonth('paid_at', $now->month)
                    ->whereYear('paid_at', $now->year);

                $totalReceived = (int) $invoiceQuery->sum('amount');
                $invoiceCount  = $invoiceQuery->count();

                // 🔹 Setoran admin bulan ini
                $totalDeposited = (int) AdminDeposit::where('admin_id', $admin->id)
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)
                    ->sum('amount');

                return [
                    'admin_id'        => $admin->id,
                    'admin_name'      => $admin->name,
                    'invoice_count'   => $invoiceCount,
                    'total_received' => $totalReceived,
                    'total_deposited' => $totalDeposited,
                    'remaining'      => max(0, $totalReceived - $totalDeposited),
                ];
            });


            return ResponseFormatter::success($data, 'Data rekap berhasil di tampilkan');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
