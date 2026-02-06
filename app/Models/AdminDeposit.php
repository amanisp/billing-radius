<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;


class AdminDeposit extends Model
{
    protected $fillable = [
        'group_id',
        'admin_id',
        'created_by',
        'amount',
        'note'
    ];

    public function scopeThisMonth($query, Carbon $date)
    {
        return $query
            ->whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
