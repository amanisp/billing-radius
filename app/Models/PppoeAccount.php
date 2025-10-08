<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PppoeAccount extends Model
{
    protected $fillable = [
        'member_id',
        'username',
        'password',
        'internet_number',
        'billing_active',
        'billing_type',
        'billing_period',
        'active_date',
        'next_inv_date',
        'ppn',
        'discount',
        'group_id',
        'profile_id',
        'area_id',
        'optical_id',
        'isolir'
    ];


    public static function generateNomorLayanan($mitraId)
    {
        $kodePT = "15342"; // Kode PT tetap

        // Ambil nomor layanan terakhir untuk mitra ini
        $lastNumber = self::where('group_id', $mitraId)->max('internet_number');

        // Jika sudah ada nomor sebelumnya, tambahkan +1
        if ($lastNumber) {
            $incrementalPart = (int)substr($lastNumber, -7) + 1;
        } else {
            $incrementalPart = 1; // Mulai dari 1 jika belum ada data
        }

        // Format bagian incremental menjadi 7 digit (contoh: 1 → 0000001)
        $formattedIncrement = str_pad($incrementalPart, 7, '0', STR_PAD_LEFT);

        // Gabungkan KODE_PT + group_id + Increment
        return $kodePT . $mitraId . $formattedIncrement;
    }

    public function profile()
    {
        return $this->belongsTo(Profiles::class, 'profile_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }

    public function globalSet()
    {
        return $this->belongsTo(globalSettings::class, 'group_id');
    }
}
