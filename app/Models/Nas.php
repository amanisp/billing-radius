<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nas extends Model
{
    protected $fillable = [
        'name',
        'group_id',
        "ip_router",
        "ip_radius",
        "secret",
    ];
}
