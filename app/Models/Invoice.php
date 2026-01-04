<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'member_id',
        'connection_id',
        'payer_id',
        'invoice_type',
        'start_date',
        'due_date',
        'subscription_period',
        'inv_number',
        'amount',
        'status',
        'group_id',
        'payment_url',
        'payment_method',
        'paid_at'
    ];

    // Invoice.php
    public function payer()
    {
        return $this->belongsTo(Mitra::class, 'payer_id');
    }
}
