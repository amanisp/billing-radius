<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mitra extends Model
{
    use HasFactory;

    protected $table = 'mitras';

    protected $fillable = [
        'name',
        'nik',
        'ktpImg',
        'area_id',
        'pop_id',
        'capacity',
        'price',
        'ppn',
        'bhpuso',
        'kso',
        'transmitter',
        'active_date',
        'email',
        'phone_number',
        'address',
        'segmentasi',
        'nomor_pelanggan'
    ];

    protected $casts = [
        'ppn' => 'boolean',
        'bhpuso' => 'boolean',
        'kso' => 'boolean',
        'active_date' => 'date',
    ];

    // Relasi ke Area
    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    // Relasi ke OpticalDist (POP)
    public function pop()
    {
        return $this->belongsTo(OpticalDist::class, 'pop_id');
    }
}
