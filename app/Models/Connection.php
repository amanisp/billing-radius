<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Profiles;
use App\Models\Groups;
use App\Models\Nas;
use App\Models\GlobalSettings;
use App\Models\Area;
use App\Models\OpticalDist;


class Connection extends Model
{
    protected $fillable = [
        'group_id',
        'type',
        'username',
        'password',
        'mac_address',
        'profile_id',
        'area_id',
        'optical_id',
        'internet_number',
        'isolir',
        'nas_id',
    ];

    protected $casts = [
        'isolir' => 'boolean',
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

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function optical()
    {
        return $this->belongsTo(OpticalDist::class, 'optical_id');
    }

    public function member()
    {
        return $this->hasOne(Member::class, 'connection_id');
    }

    public function latestInvoice()
    {
        return $this->hasOne(InvoiceHomepass::class, 'connection_id')
            ->latestOfMany(); // ambil hanya yang terbaru
    }


    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }

    public function nas()
    {
        return $this->belongsTo(Nas::class, 'nas_id');
    }

    public function globalSet()
    {
        return $this->belongsTo(GlobalSettings::class, 'group_id');
    }
}
