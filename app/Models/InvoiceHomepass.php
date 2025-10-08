<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceHomepass extends Model
{
    protected $fillable = [
        'connection_id',
        'payer_id',
        'member_id',
        'invoice_type',
        'start_date',
        'due_date',
        'subscription_period',
        'inv_number',
        'amount',
        'status',
        'group_id',
        'next_inv_date',
        'payment_url',
        'payment_method',
        'paid_at'
    ];


    public function payer()
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
    public function connection()
    {
        return $this->belongsTo(Connection::class, 'connection_id');
    }
    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }
}
