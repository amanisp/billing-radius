<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'group_id',
        'template_type',
        'content',
    ];

    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }


    public function scopeByType($query, $type)
    {
        return $query->where('template_type', $type);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('group_id');
    }

}
