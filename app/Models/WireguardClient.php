<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WireguardClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'ip_address',
        'public_key',
        'config',
    ];
}
