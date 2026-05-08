<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappTemplates extends Model
{
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'group_id',
        'template_type',
        'content',
    ];
}
