<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $fillable = [
        'name',
        'group_id',
        'area_code',
    ];

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

    public function opticalCount()
    {
        return $this->opticals()->count();
    }

    public function connection()
    {
        return $this->hasMany(Connection::class, 'area_id');
    }

    public function pppoeCount()
    {
        return $this->connection()->count();
    }

    public function assignedTechnicians()
    {
        return $this->belongsToMany(User::class, 'technician_areas', 'area_id', 'user_id')
            ->whereIn('role', ['teknisi', 'kasir'])
            ->withTimestamps();
    }

    public function scopeSuperadminAreas($query)
    {
        return $query->where('group_id', 1);
    }
}
