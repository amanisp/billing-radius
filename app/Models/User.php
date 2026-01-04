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
        'name',
        'role',
        'group_id',
        'area_id',
        'email',
        'phone_number',
        'nip',
        'customer_number',
        'register',
        'payment',
        'address',
        'username',
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
            'register' => 'date',
        ];
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function teknisi()
    {
        return $this->hasMany(User::class, 'group_id')->where('role', 'teknisi');
    }

    public function kasir()
    {
        return $this->hasMany(User::class, 'group_id')->where('role', 'kasir');
    }

    public function assignedAreas()
    {
        return $this->belongsToMany(Area::class, 'technician_areas', 'user_id', 'area_id')
            ->withTimestamps();
    }

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
