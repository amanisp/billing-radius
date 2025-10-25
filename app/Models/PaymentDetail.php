<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PaymentDetail extends Model
{
    protected $fillable = [
        'group_id',
        'payment_type',
        'billing_period',
        'active_date',
        'amount',
        'discount',
        'ppn',
    ];

    protected $casts = [
        'active_date' => 'date',
        'last_invoice' => 'date',
    ];

    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }

    // Relasi ke Member
    public function member()
    {
        return $this->hasOne(Member::class, 'payment_detail_id');
    }

    // Helper: Calculate total amount with PPN and discount
    public function calculateTotalAmount($periode = 1)
    {
        $baseAmount = $this->amount * $periode;
        $ppnAmount = ($baseAmount * $this->ppn) / 100;
        $discountAmount = ($baseAmount * $this->discount) / 100;

        return $baseAmount + $ppnAmount - $discountAmount;
    }

    // Helper: Get next invoice start date
    public function getNextInvoiceStartDate()
    {
        if ($this->last_invoice) {
            return Carbon::parse($this->last_invoice);
        }

        if ($this->active_date) {
            return Carbon::parse($this->active_date);
        }

        return Carbon::now();
    }

    // Helper: Update last invoice date
    public function updateLastInvoice($dueDate)
    {
        $this->update([
            'last_invoice' => $dueDate
        ]);
    }

    // Helper: Reset last invoice (untuk rollback)
    public function resetLastInvoice()
    {
        $this->update([
            'last_invoice' => null
        ]);
    }

    // Accessor: Format amount with currency
    public function getFormattedAmountAttribute()
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    // Accessor: Format last invoice date
    public function getFormattedLastInvoiceAttribute()
    {
        if (!$this->last_invoice) {
            return '-';
        }
        return Carbon::parse($this->last_invoice)->format('d M Y');
    }

    // Check if payment type is prepaid (prabayar)
    public function isPrepaid()
    {
        return strtolower($this->payment_type) === 'prabayar';
    }

    // Check if payment type is postpaid (pascabayar)
    public function isPostpaid()
    {
        return strtolower($this->payment_type) === 'pascabayar';
    }
}
