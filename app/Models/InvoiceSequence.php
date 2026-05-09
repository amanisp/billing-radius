<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSequence extends Model
{
    protected $table = 'invoice_sequences';
    protected $fillable = [
        'group_id',
        'area_id',
        'type',
        'year_month',
        'last_number',
    ];
}
