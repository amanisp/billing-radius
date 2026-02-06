<?php
// app/Models/WhatsappMessageLog.php - FULL FIXED
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessageLog extends Model
{
    protected $table = 'whatsapp_message_logs';

    protected $fillable = [
        'group_id',
        'recipient',
        'message',
        'status',
        'type',
        'sent_at',
        'response_data'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'response_data' => 'array'
    ];

    // Tambah jika belum ada
    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }
}
