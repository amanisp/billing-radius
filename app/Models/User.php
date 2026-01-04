<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    protected $connection = 'mysql';

    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'name',
        'role',
        'group_id',
        'email',
        'phone_number',
        'email_verified_at',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relasi ke mitra
     */
    public function mitra()
    {
        return $this->belongsTo(Mitra::class, 'group_id');
    }

    /**
     * Company memiliki banyak teknisi
     */
    public function teknisi()
    {
        return $this->hasMany(User::class, 'group_id')->where('role', 'teknisi');
    }

    /**
     * Company memiliki banyak kasir
     */
    public function kasir()
    {
        return $this->hasMany(User::class, 'group_id')->where('role', 'kasir');
    }

    /**
     * Relasi teknisi dengan area yang di-assign (TAMBAHKAN INI)
     */
    public function assignedAreas()
    {
        return $this->belongsToMany(Area::class, 'technician_areas', 'user_id', 'area_id')
            ->withTimestamps();
    }

    /**
     * Role Check Methods
     */
    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isMitra(): bool
    {
        return $this->role === 'mitra';
    }

    public function isTeknisi(): bool
    {
        return $this->role === 'teknisi';
    }

    public function isKasir(): bool
    {
        return $this->role === 'kasir';
    }

    public function setPhoneNumberAttribute($value)
    {
        $value = preg_replace('/\D/', '', $value);

        if (substr($value, 0, 1) === '0') {
            $value = '62' . substr($value, 1);
        }

        $this->attributes['phone_number'] = $value;
    }
}
