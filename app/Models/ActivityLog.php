<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'time',
        'operation',
        'table_name',
        'username',
        'role',
        'ip_address',
        'session_id',
        'details'
    ];

    protected $casts = [
        'time' => 'datetime',
        'details' => 'array'
    ];
}
