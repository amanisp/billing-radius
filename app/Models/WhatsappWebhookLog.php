<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappWebhookLog extends Model
{
    protected $fillable = [
        'group_id',
        'phone_number',
        'status',
        'device_info',
        'message_logs',
        'last_updated'
    ];

    protected $casts = [
        'device_info' => 'array',
        'message_logs' => 'array',
        'last_updated' => 'datetime'
    ];

    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }
}
