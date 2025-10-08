<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profiles extends Model
{
    protected $fillable = [
        'name',
        'group_id',
        "price",
        "rate_rx",
        "rate_tx",
        "burst_rx",
        "burst_tx",
        "threshold_rx",
        "threshold_tx",
        "time_rx",
        "time_tx",
        "priority"
    ];
}
