<?php

namespace App\Http\Controllers;

use App\Models\AccountingTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class AccountingController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $now = Carbon::now();

        // Build base query
        $baseQuery = AccountingTransaction::where('group_id', $user->group_id);

        // PEMASUKAN BULAN INI
        $monthlyIncome = (clone $baseQuery)
            ->where('transaction_type', 'income')
            ->whereMonth('transaction_date', $now->month)
            ->whereYear('transaction_date', $now->year)
            ->sum('amount');

        // PENGELUARAN BULAN INI
        $monthlyExpense = (clone $baseQuery)
            ->where('transaction_type', 'expense')
            ->whereMonth('transaction_date', $now->month)
            ->whereYear('transaction_date', $now->year)
            ->sum('amount');

        // PROFIT BULAN INI
        $monthlyProfit = $monthlyIncome - $monthlyExpense;

        // BREAKDOWN PEMASUKAN
        $monthlyCash = (clone $baseQuery)
            ->where('transaction_type', 'income')
            ->where('payment_method', 'cash')
            ->whereMonth('transaction_date', $now->month)
            ->whereYear('transaction_date', $now->year)
            ->sum('amount');

        $monthlyTransfer = (clone $baseQuery)
            ->where('transaction_type', 'income')
            ->where('payment_method', 'bank_transfer')
            ->whereMonth('transaction_date', $now->month)
            ->whereYear('transaction_date', $now->year)
            ->sum('amount');

        $monthlyGateway = (clone $baseQuery)
            ->where('transaction_type', 'income')
            ->where('payment_method', 'payment_gateway')
            ->whereMonth('transaction_date', $now->month)
            ->whereYear('transaction_date', $now->year)
            ->sum('amount');

        // Get transaction count
        $totalTransactions = (clone $baseQuery)
            ->whereMonth('transaction_date', $now->month)
            ->whereYear('transaction_date', $now->year)
            ->count();

        return view('pages.accounting.index', compact(
            'monthlyIncome',
            'monthlyExpense',
            'monthlyProfit',
            'monthlyCash',
            'monthlyTransfer',
            'monthlyGateway',
            'totalTransactions'
        ));
    }

    public function getData(Request $request)
    {
        $user = Auth::user();
        $query = AccountingTransaction::with(['receiver', 'payer', 'invoice'])
            ->where('group_id', $user->group_id)
            ->orderBy('transaction_date', 'desc');

        // Filter by transaction type
        if ($request->has('transaction_type') && $request->transaction_type != '') {
            $query->where('transaction_type', $request->transaction_type);
        }

        // Filter by category
        if ($request->has('category') && $request->category != '') {
            $query->where('category', $request->category);
        }

        // Filter by payment method (untuk income saja)
        if ($request->has('payment_method') && $request->payment_method != '') {
            $query->where('payment_method', $request->payment_method);
        }

        // Date range filter
        if ($request->has('date_from') && $request->date_from != '') {
            $query->where('transaction_date', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->has('date_to') && $request->date_to != '') {
            $query->where('transaction_date', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('type_badge', function ($row) {
                if ($row->transaction_type === 'income') {
                    return '<span class="badge bg-success">Pemasukan</span>';
                } else {
                    return '<span class="badge bg-danger">Pengeluaran</span>';
                }
            })
            ->addColumn('category_label', function ($row) {
                return $row->category_label;
            })
            ->addColumn('reference', function ($row) {
                // Untuk income: tampilkan invoice number
                // Untuk expense: tampilkan description
                if ($row->transaction_type === 'income' && $row->invoice_number) {
                    return $row->invoice_number;
                }
                return $row->description ?? '-';
            })
            ->addColumn('party_name', function ($row) {
                // Untuk income: nama pelanggan
                // Untuk expense: "-" atau vendor name (jika ada)
                return $row->member_name ?? '-';
            })
            ->addColumn('formatted_amount', function ($row) {
                $color = $row->transaction_type === 'income' ? 'text-success' : 'text-danger';
                $sign = $row->transaction_type === 'income' ? '+' : '-';
                return '<span class="' . $color . '">' . $sign . ' ' . $row->formatted_amount . '</span>';
            })
            ->addColumn('payment_method_label', function ($row) {
                if ($row->transaction_type === 'expense') return '-';

                $badges = [
                    'cash' => '<span class="badge bg-success">Tunai</span>',
                    'bank_transfer' => '<span class="badge bg-primary">Transfer Bank</span>',
                    'payment_gateway' => '<span class="badge bg-info">Payment Gateway</span>',
                ];
                return $badges[$row->payment_method] ?? '-';
            })
            ->addColumn('transaction_date_formatted', function ($row) {
                return Carbon::parse($row->transaction_date)->format('d/m/Y H:i');
            })
            ->addColumn('user_name', function ($row) {
                // Untuk income: received_by
                // Untuk expense: paid_by
                if ($row->transaction_type === 'income') {
                    return $row->receiver ? $row->receiver->name : 'System';
                } else {
                    return $row->payer ? $row->payer->name : '-';
                }
            })
            ->addColumn('action', function ($row) {
                $buttons = '<button class="btn btn-sm btn-outline-info btn-detail" data-id="' . $row->id . '">
                    <i class="fa-solid fa-eye"></i> Detail
                </button>';

                // Hanya admin/mitra yang bisa hapus
                if (Auth::user()->role === 'mitra') {
                    $buttons .= ' <button class="btn btn-sm btn-outline-danger btn-delete" data-id="' . $row->id . '">
                        <i class="fa-solid fa-trash"></i>
                    </button>';
                }

                return $buttons;
            })
            ->rawColumns(['type_badge', 'formatted_amount', 'payment_method_label', 'action'])
            ->make(true);
    }

    public function show($id)
    {
        $user = Auth::user();
        $transaction = AccountingTransaction::with(['receiver', 'payer', 'invoice.member'])
            ->where('group_id', $user->group_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $transaction->id,
                'transaction_type' => $transaction->transaction_type,
                'category' => $transaction->category,
                'category_label' => $transaction->category_label,
                'invoice_number' => $transaction->invoice_number,
                'member_name' => $transaction->member_name,
                'amount' => $transaction->amount,
                'formatted_amount' => $transaction->formatted_amount,
                'payment_method' => $transaction->payment_method,
                'payment_method_label' => $transaction->payment_method_label,
                'transaction_date' => Carbon::parse($transaction->transaction_date)->format('d/m/Y H:i'),
                'account_name' => $transaction->account_name,
                'account_number' => $transaction->account_number,
                'bank_name' => $transaction->bank_name,
                'description' => $transaction->description,
                'notes' => $transaction->notes,
                'receipt_number' => $transaction->receipt_number,
                'user_name' => $transaction->transaction_type === 'income'
                    ? ($transaction->receiver ? $transaction->receiver->name : 'System')
                    : ($transaction->payer ? $transaction->payer->name : '-'),
                'invoice_period' => $transaction->invoice ? $transaction->invoice->subscription_period : '-',
            ]
        ]);
    }

    public function getStats(Request $request)
    {
        $user = Auth::user();
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = AccountingTransaction::where('group_id', $user->group_id);

        if ($dateFrom && $dateTo) {
            $query->whereBetween('transaction_date', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay()
            ]);
        }

        // Total Income & Expense
        $totalIncome = (clone $query)->where('transaction_type', 'income')->sum('amount');
        $totalExpense = (clone $query)->where('transaction_type', 'expense')->sum('amount');
        $netProfit = $totalIncome - $totalExpense;

        // Income breakdown by payment method
        $cashTotal = (clone $query)
            ->where('transaction_type', 'income')
            ->where('payment_method', 'cash')
            ->sum('amount');

        $transferTotal = (clone $query)
            ->where('transaction_type', 'income')
            ->where('payment_method', 'bank_transfer')
            ->sum('amount');

        $gatewayTotal = (clone $query)
            ->where('transaction_type', 'income')
            ->where('payment_method', 'payment_gateway')
            ->sum('amount');

        // Income breakdown by category
        $incomeBreakdown = (clone $query)
            ->where('transaction_type', 'income')
            ->select('category', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->get();

        // Expense breakdown by category
        $expenseBreakdown = (clone $query)
            ->where('transaction_type', 'expense')
            ->select('category', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->get();

        // Daily breakdown (last 30 days)
        $dailyBreakdown = (clone $query)
            ->select(
                DB::raw('DATE(transaction_date) as date'),
                'transaction_type',
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->where('transaction_date', '>=', Carbon::now()->subDays(30))
            ->groupBy('date', 'transaction_type')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'net_profit' => $netProfit,
                'cash_total' => $cashTotal,
                'transfer_total' => $transferTotal,
                'gateway_total' => $gatewayTotal,
                'income_breakdown' => $incomeBreakdown,
                'expense_breakdown' => $expenseBreakdown,
                'daily_breakdown' => $dailyBreakdown,
            ]
        ]);
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $transactionType = $request->query('transaction_type');

        $query = AccountingTransaction::with(['receiver', 'payer', 'invoice'])
            ->where('group_id', $user->group_id)
            ->orderBy('transaction_date', 'desc');

        if ($dateFrom && $dateTo) {
            $query->whereBetween('transaction_date', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay()
            ]);
        }

        if ($transactionType) {
            $query->where('transaction_type', $transactionType);
        }

        $transactions = $query->get();

        // Generate CSV
        $filename = 'pembukuan_' . date('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');

            // Header
            fputcsv($file, [
                'Tanggal',
                'Tipe',
                'Kategori',
                'Referensi',
                'Pihak Terkait',
                'Jumlah',
                'Metode Pembayaran',
                'User',
                'Catatan'
            ]);

            // Data
            foreach ($transactions as $transaction) {
                $type = $transaction->transaction_type === 'income' ? 'Pemasukan' : 'Pengeluaran';
                $reference = $transaction->invoice_number ?? $transaction->description ?? '-';
                $party = $transaction->member_name ?? '-';
                $user = $transaction->transaction_type === 'income'
                    ? ($transaction->receiver ? $transaction->receiver->name : 'System')
                    : ($transaction->payer ? $transaction->payer->name : '-');

                fputcsv($file, [
                    Carbon::parse($transaction->transaction_date)->format('d/m/Y H:i'),
                    $type,
                    $transaction->category_label,
                    $reference,
                    $party,
                    $transaction->amount,
                    $transaction->payment_method_label ?? '-',
                    $user,
                    $transaction->notes ?? '-'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ====================================
    // METHOD UNTUK TAMBAH PENGELUARAN
    // ====================================

    public function storeExpense(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'category' => 'required|in:salary,installation_cost,equipment_repair,bandwidth,collector_fee,utility,marketing,other_expense',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'receipt_number' => 'nullable|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            $expense = AccountingTransaction::create([
                'group_id' => $user->group_id,
                'transaction_type' => 'expense',
                'category' => $request->category,
                'amount' => $request->amount,
                'paid_by' => $user->id,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'notes' => $request->notes,
                'receipt_number' => $request->receipt_number,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengeluaran berhasil dicatat',
                'data' => $expense
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat pengeluaran: ' . $e->getMessage()
            ], 500);
        }
    }

    // ====================================
    // METHOD UNTUK TAMBAH PEMASUKAN LAIN
    // ====================================

    public function storeOtherIncome(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'category' => 'required|in:installation_fee,late_fee,other_income',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,bank_transfer',
            'transaction_date' => 'required|date',
            'description' => 'required|string|max:255',
            'member_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'receipt_number' => 'nullable|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            $income = AccountingTransaction::create([
                'group_id' => $user->group_id,
                'transaction_type' => 'income',
                'category' => $request->category,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'received_by' => $user->id,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'member_name' => $request->member_name,
                'notes' => $request->notes,
                'receipt_number' => $request->receipt_number,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pemasukan berhasil dicatat',
                'data' => $income
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat pemasukan: ' . $e->getMessage()
            ], 500);
        }
    }

    // ====================================
    // METHOD UNTUK HAPUS TRANSAKSI
    // ====================================

    public function destroy($id)
    {
        $user = Auth::user();

        // Hanya mitra/admin yang bisa hapus
        if ($user->role !== 'mitra') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus transaksi'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $transaction = AccountingTransaction::where('group_id', $user->group_id)
                ->findOrFail($id);

            // Jika ini transaksi dari invoice, jangan bisa dihapus langsung
            // Harus melalui cancel invoice
            if ($transaction->invoice_id && $transaction->category === 'subscription_payment') {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi dari invoice tidak bisa dihapus langsung. Gunakan fitur Cancel Payment di halaman Invoice.'
                ], 400);
            }

            $transaction->delete(); // soft delete

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus transaksi: ' . $e->getMessage()
            ], 500);
        }
    }
}
