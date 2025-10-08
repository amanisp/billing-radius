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
        // Hapus karakter selain angka
        $value = preg_replace('/\D/', '', $value);

        // Ubah 08 menjadi 62
        if (substr($value, 0, 1) === '0') {
            $value = '62' . substr($value, 1);
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

    // Hitung jumlah akun PPPoE yang menggunakan ODP ini
    public function serviceCount()
    {
        return $this->serviceActive()->count();
    }

    public function paymentDetail()
    {
        return $this->belongsTo(PaymentDetail::class, 'payment_detail_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'member_id');
    }

}
