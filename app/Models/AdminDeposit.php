<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminDeposit extends Model
{
    protected $fillable = [
        'group_id',
        'admin_id',
        'created_by',
        'amount',
        'note'
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
