<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VpnUsers extends Model
{
    protected $fillable = [
        'name',
        'group_id',
        "username",
        "password",
        "ip_address",
    ];
}
