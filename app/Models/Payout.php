<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    protected $fillable = [
        'payout_url',
        'email',
        'exp_link',
        'external_id',
        'amount',
        'status',
        'group_id',
    ];
}
