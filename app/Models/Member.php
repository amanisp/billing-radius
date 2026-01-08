<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = [
        'group_id',
        'fullname',
        'phone_number',
        'email',
        'id_card',
        'connection_id',
        'billing',
        'payment_detail_id',
        'invoice_id',
        'area_id',
        'optical_id',
        'address',
    ];

    protected $casts = [
        'billing' => 'boolean',
    ];

    public function setPhoneNumberAttribute($value)
    {
        // Hapus semua karakter selain angka
        $value = preg_replace('/\D/', '', $value);

        // Jika diawali 0 → ganti 62
        if (str_starts_with($value, '0')) {
            $value = '62' . substr($value, 1);
        }
        // Jika diawali 82 → tambahkan 62
        elseif (str_starts_with($value, '82')) {
            $value = '62' . $value;
        }

        $this->attributes['phone_number'] = $value;
    }


    public function serviceActive()
    {
        return $this->hasMany(PppoeAccount::class, 'member_id');
    }

    public function connection()
    {
        return $this->belongsTo(Connection::class, 'connection_id');
    }

    // Hitung jumlah akun PPPoE yang menggunakan member ini
    public function serviceCount()
    {
        return $this->serviceActive()->count();
    }

    public function paymentDetail()
    {
        return $this->belongsTo(PaymentDetail::class, 'payment_detail_id');
    }

    // PERBAIKAN: Relasi ke InvoiceHomepass (bukan Invoice)
    public function invoices()
    {
        return $this->hasMany(InvoiceHomepass::class, 'member_id');
    }

    // Relasi ke Group
    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }

    // Helper: Get latest paid invoice
    public function latestPaidInvoice()
    {
        return $this->hasOne(InvoiceHomepass::class, 'member_id')
            ->where('status', 'paid')
            ->latest('due_date');
    }

    // Helper: Get latest invoice (paid or unpaid)
    public function latestInvoice()
    {
        return $this->hasOne(InvoiceHomepass::class, 'member_id')
            ->latest('created_at');
    }

    // Helper: Get unpaid invoices
    public function unpaidInvoices()
    {
        return $this->hasMany(InvoiceHomepass::class, 'member_id')
            ->where('status', 'unpaid')
            ->orderBy('due_date', 'asc');
    }

    // Accessor: Get next invoice date
    public function getNextInvoiceDateAttribute()
    {
        // Prioritas: last_invoice dari payment_detail
        if ($this->paymentDetail && $this->paymentDetail->last_invoice) {
            return $this->paymentDetail->last_invoice;
        }

        // Fallback: active_date dari payment_detail
        if ($this->paymentDetail && $this->paymentDetail->active_date) {
            return $this->paymentDetail->active_date;
        }

        return null;
    }

    // Helper: Check if member has unpaid invoices
    public function hasUnpaidInvoices()
    {
        return $this->invoices()
            ->where('status', 'unpaid')
            ->exists();
    }

    // Helper: Get total unpaid amount
    public function getTotalUnpaidAmount()
    {
        return $this->invoices()
            ->where('status', 'unpaid')
            ->sum('amount');
    }

    // Helper: Check if member can create new invoice
    public function canCreateInvoice($targetMonth, $targetYear)
    {
        // Cek apakah sudah ada invoice untuk bulan & tahun tersebut
        return !$this->invoices()
            ->whereMonth('start_date', $targetMonth)
            ->whereYear('start_date', $targetYear)
            ->exists();
    }

    // Scope untuk filter member yang billing aktif
    public function scopeBillingActive($query)
    {
        return $query->where('billing', true);
    }

    // Scope untuk filter by group
    public function scopeByGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }
}
