<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountingTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'group_id',
        'transaction_type',
        'category',
        'invoice_id',
        'invoice_number',
        'member_name',
        'amount',
        'payment_method',
        'account_name',
        'account_number',
        'bank_name',
        'received_by',
        'paid_by',
        'transaction_date',
        'description',
        'notes',
        'receipt_number',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }

    public function invoice()
    {
        return $this->belongsTo(InvoiceHomepass::class, 'invoice_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }


    public function scopeIncome($query)
    {
        return $query->where('transaction_type', 'income');
    }

    public function scopeExpense($query)
    {
        return $query->where('transaction_type', 'expense');
    }

    public function scopeByMonth($query, $month, $year)
    {
        return $query->whereMonth('transaction_date', $month)
                     ->whereYear('transaction_date', $year);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }


    public function getFormattedAmountAttribute()
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    public function getPaymentMethodLabelAttribute()
    {
        if (!$this->payment_method) return '-';

        $labels = [
            'cash' => 'Tunai',
            'bank_transfer' => 'Transfer Bank',
            'payment_gateway' => 'Payment Gateway',
        ];

        return $labels[$this->payment_method] ?? $this->payment_method;
    }

    public function getCategoryLabelAttribute()
    {
        $categories = [
            // INCOME
            'subscription_payment' => 'Pembayaran Langganan',
            'installation_fee' => 'Biaya Pemasangan',
            'late_fee' => 'Denda Keterlambatan',
            'other_income' => 'Pemasukan Lain-lain',

            // EXPENSE
            'salary' => 'Gaji Karyawan',
            'installation_cost' => 'Biaya Pasang Baru',
            'equipment_repair' => 'Perbaikan Alat',
            'bandwidth' => 'Biaya Bandwidth',
            'collector_fee' => 'Bayar Kang Tagih',
            'utility' => 'Listrik/PDAM/Pulsa',
            'marketing' => 'Marketing',
            'other_expense' => 'Pengeluaran Lain-lain',
        ];

        return $categories[$this->category] ?? ucwords(str_replace('_', ' ', $this->category));
    }

    public function getTransactionTypeLabelAttribute()
    {
        return $this->transaction_type === 'income' ? 'Pemasukan' : 'Pengeluaran';
    }


    /**
     * Get total income for specific period
     */
    public static function getTotalIncome($groupId, $startDate = null, $endDate = null)
    {
        $query = self::where('group_id', $groupId)
                    ->where('transaction_type', 'income');

        if ($startDate && $endDate) {
            $query->whereBetween('transaction_date', [$startDate, $endDate]);
        }

        return $query->sum('amount');
    }

    /**
     * Get total expense for specific period
     */
    public static function getTotalExpense($groupId, $startDate = null, $endDate = null)
    {
        $query = self::where('group_id', $groupId)
                    ->where('transaction_type', 'expense');

        if ($startDate && $endDate) {
            $query->whereBetween('transaction_date', [$startDate, $endDate]);
        }

        return $query->sum('amount');
    }

    /**
     * Get net profit (income - expense)
     */
    public static function getNetProfit($groupId, $startDate = null, $endDate = null)
    {
        $income = self::getTotalIncome($groupId, $startDate, $endDate);
        $expense = self::getTotalExpense($groupId, $startDate, $endDate);

        return $income - $expense;
    }

    /**
     * Get income breakdown by category
     */
    public static function getIncomeBreakdown($groupId, $startDate = null, $endDate = null)
    {
        $query = self::where('group_id', $groupId)
                    ->where('transaction_type', 'income')
                    ->select('category', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
                    ->groupBy('category');

        if ($startDate && $endDate) {
            $query->whereBetween('transaction_date', [$startDate, $endDate]);
        }

        return $query->get();
    }

    /**
     * Get expense breakdown by category
     */
    public static function getExpenseBreakdown($groupId, $startDate = null, $endDate = null)
    {
        $query = self::where('group_id', $groupId)
                    ->where('transaction_type', 'expense')
                    ->select('category', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
                    ->groupBy('category');

        if ($startDate && $endDate) {
            $query->whereBetween('transaction_date', [$startDate, $endDate]);
        }

        return $query->get();
    }

    /**
     * Get income by payment method
     */
    public static function getIncomeByPaymentMethod($groupId, $startDate = null, $endDate = null)
    {
        $query = self::where('group_id', $groupId)
                    ->where('transaction_type', 'income')
                    ->select('payment_method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
                    ->groupBy('payment_method');

        if ($startDate && $endDate) {
            $query->whereBetween('transaction_date', [$startDate, $endDate]);
        }

        return $query->get();
    }

    /**
     * Get daily cash flow
     */
    public static function getDailyCashFlow($groupId, $days = 30)
    {
        $startDate = Carbon::now()->subDays($days);

        return self::where('group_id', $groupId)
                   ->where('transaction_date', '>=', $startDate)
                   ->select(
                       DB::raw('DATE(transaction_date) as date'),
                       'transaction_type',
                       DB::raw('SUM(amount) as total')
                   )
                   ->groupBy('date', 'transaction_type')
                   ->orderBy('date')
                   ->get();
    }

    /**
     * Get monthly summary
     */
    public static function getMonthlySummary($groupId, $month, $year)
    {
        $income = self::where('group_id', $groupId)
                     ->where('transaction_type', 'income')
                     ->whereMonth('transaction_date', $month)
                     ->whereYear('transaction_date', $year)
                     ->sum('amount');

        $expense = self::where('group_id', $groupId)
                      ->where('transaction_type', 'expense')
                      ->whereMonth('transaction_date', $month)
                      ->whereYear('transaction_date', $year)
                      ->sum('amount');

        return [
            'income' => $income,
            'expense' => $expense,
            'profit' => $income - $expense,
        ];
    }
}
