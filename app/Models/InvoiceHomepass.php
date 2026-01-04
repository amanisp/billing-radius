<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceHomepass extends Model
{
    use HasFactory;

    protected $table = 'invoice_homepasses';

    protected $fillable = [
        'payer_id',
        'group_id',
        'member_id',
        'payment_method',
        'invoice_type',
        'start_date',
        'due_date',
        'paid_at',
        'subscription_period',
        'inv_number',
        'payment_url',
        'amount',
        'status',
        'connection_id'
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'date',
        'amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function payer()
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function connection()
    {
        return $this->belongsTo(Connection::class, 'connection_id');
    }

    // Scopes
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByYear($query, $year)
    {
        return $query->whereYear('paid_at', $year);
    }

    public function scopeByMonth($query, $month, $year = null)
    {
        $query->whereMonth('paid_at', $month);

        if ($year) {
            $query->whereYear('paid_at', $year);
        }

        return $query;
    }

    public function scopeByGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }
}
