<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    protected $table = 'import_batches';

    protected $fillable = [
        'group_id',
        'type',
        'status',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'started_at',
        'created_at',
        'updated_at'
    ];
}
