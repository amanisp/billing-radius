<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportErrorLog extends Model
{
    protected $table = 'import_error_logs';

    protected $fillable = [
        'import_batch_id',
        'row_number',
        'username',
        'error_type',
        'error_message',
        'row_data',
        'group_id',
        'additional_data',
        'resolved',
        'resolved_by',
        'resolved_at',
        'resolution_notes'
    ];

    protected $casts = [
        'row_data' => 'array',
        'additional_data' => 'array',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime'
    ];

    // Scope untuk filter berdasarkan batch
    public function scopeByBatch($query, $batchId)
    {
        return $query->where('import_batch_id', $batchId);
    }

    // Scope untuk filter error yang belum resolved
    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }

    // Scope untuk filter berdasarkan error type
    public function scopeByErrorType($query, $type)
    {
        return $query->where('error_type', $type);
    }

    // Method untuk mark as resolved
    public function markAsResolved($userId = null, $notes = null)
    {
        $this->update([
            'resolved' => true,
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_notes' => $notes
        ]);
    }
}
