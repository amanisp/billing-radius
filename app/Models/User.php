<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'role',
        'group_id', // Mengganti group_id menjadi company_id
        'email',
        'phone_number',
        'email_verified_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Boot method untuk menangani event model.
     */

    /**
     * Relasi ke company (jika user adalah bagian dari suatu perusahaan)
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
        // Hapus karakter selain angka
        $value = preg_replace('/\D/', '', $value);

        // Ubah 08 menjadi 62
        if (substr($value, 0, 1) === '0') {
            $value = '62' . substr($value, 1);
        }

        $this->attributes['phone_number'] = $value;
    }
}
