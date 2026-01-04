<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Process\Process;

class OpticalDist extends Model
{
    protected $fillable = ['name', 'group_id', 'area_id', 'lat', 'lng', 'ip_public', 'device_name', 'capacity', 'type'];

    public function mitras()
    {
        return $this->hasMany(Mitra::class, 'pop_id');
    }


    public function mitraCount()
    {
        return $this->mitras()->count();
    }

    public function connection()
    {
        return $this->hasMany(Connection::class, 'optical_id');
    }

    // Hitung jumlah akun PPPoE yang menggunakan ODP ini
    public function pppoeCount()
    {
        return $this->connection()->count();
    }
}
