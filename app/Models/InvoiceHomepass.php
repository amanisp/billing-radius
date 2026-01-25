<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceHomepass extends Model
{

    protected $table = 'invoice_homepasses';

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
        'payment_method',
        'status',
        'paid_at',
        'group_id',
        'wa_status',
        'wa_sent_at',
        'wa_error_message',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'wa_sent_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function connection()
    {
        return $this->belongsTo(Connection::class, 'connection_id');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }

    // HELPER METHODS
    public function isWhatsappSent(): bool
    {
        return $this->wa_status === 'sent';
    }

    public function isWhatsappFailed(): bool
    {
        return in_array($this->wa_status, [
            'failed',
            'failed_permanently'
        ]);
    }

    public function getWhatsappStatusLabel(): string
    {
        $labels = [
            'not_sent' => 'Belum Dikirim',
            'pending' => 'Antri Pengiriman',
            'sent' => 'Terkirim ✓',
            'failed' => 'Gagal (Retry)',
            'failed_permanently' => 'Gagal Permanen',
        ];

        return $labels[$this->wa_status] ?? 'Status Tidak Diketahui';
    }

    public function canRetryWhatsapp(): bool
    {
        return $this->wa_status === 'failed' && !is_null($this->wa_sent_at);
    }

    public function scopeByWhatsappStatus($query, $status)
    {
        return $query->where('wa_status', $status);
    }

    public function scopeWhatsappSent($query)
    {
        return $query->where('wa_status', 'sent');
    }

    public function scopeWhatsappFailed($query)
    {
        return $query->whereIn('wa_status', ['failed', 'failed_permanently']);
    }

    public function scopeWhatsappPending($query)
    {
        return $query->where('wa_status', 'pending');
    }
}
