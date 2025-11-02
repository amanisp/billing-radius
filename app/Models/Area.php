<?php

namespace App\Models;

use Google\Service\SQLAdmin\Resource\Connect;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $fillable = ['name', 'group_id', 'area_code'];

    /**
     * Relasi Area dengan Optical (Area memiliki banyak Optical)
     */
    public function opticals()
    {
        return $this->hasMany(OpticalDist::class);
    }

    public function mitras()
    {
        return $this->hasMany(Mitra::class);
    }


    public function mitraCount()
    {
        return $this->mitras()->count();
    }


    /**
     * Menghitung jumlah ODP yang ada di Area ini
     *
     * @return int
     */
    public function opticalCount()
    {
        return $this->opticals()->count();
    }

    public function connection()
    {
        return $this->hasMany(Connection::class, 'area_id');
    }

    // Hitung jumlah akun PPPoE yang menggunakan ODP ini
    public function pppoeCount()
    {
        return $this->connection()->count();
    }
    /**
     * Relasi area dengan teknisi yang di-assign
     */
    public function assignedTechnicians()
    {
        return $this->belongsToMany(User::class, 'technician_areas', 'area_id', 'user_id')
            ->whereIn('role', ['teknisi', 'kasir'])
            ->withTimestamps();
    }
}
