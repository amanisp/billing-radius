<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;


class InvoiceHomepass extends Model
{
    protected $fillable = [
        'connection_id',
        'payer_id',
        'member_id',
        'invoice_type',
        'start_date',
        'due_date',
        'subscription_period',
        'inv_number',
        'amount',
        'status',
        'group_id',
        'payment_url',
        'payment_method',
        'paid_at'
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'amount' => 'integer',
    ];

    public function scopePaidThisMonth($query, Carbon $date)
    {
        return $query
            ->where('status', 'paid')
            ->whereMonth('paid_at', $date->month)
            ->whereYear('paid_at', $date->year);
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function connection()
    {
        return $this->belongsTo(Connection::class, 'connection_id');
    }

    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }

    // Scope untuk filter invoice by status
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    // Scope untuk filter invoice by periode
    public function scopeByMonth($query, $month, $year)
    {
        return $query->whereMonth('start_date', $month)
            ->whereYear('start_date', $year);
    }

    public function accountingTransactions()
    {
        return $this->hasMany(AccountingTransaction::class, 'invoice_id');
    }

    // Helper method untuk check apakah invoice overdue
    public function isOverdue()
    {
        if ($this->status === 'paid') {
            return false;
        }
        return $this->due_date < now();
    }
}
