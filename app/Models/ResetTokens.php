<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResetTokens extends Model
{
    protected $fillable = [
        'email',
        'token',
        'expired_at',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expired_at->isPast();
    }
}
