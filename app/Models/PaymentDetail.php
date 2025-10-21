<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Groups;

class PaymentDetail extends Model
{
    protected $fillable = [
        'group_id',
        'payment_type',
        'billing_period',
        'active_date',
        'amount',
        'discount',
        'ppn',
        'last_invoice',
    ];

    public function group()
    {
        return $this->belongsTo(Groups::class);
    }
}
