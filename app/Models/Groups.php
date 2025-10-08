<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Groups extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'wa_api_token',
        'group_type',
    ];
}
