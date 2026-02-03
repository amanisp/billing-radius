<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    // protected $table = 'radacct';

    protected $fillable = [
        'description',
        'amount',
        'category',
        'expense_date',
        'user_id',
        'group_id',
    ];

    protected $casts = [
        'expense_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
