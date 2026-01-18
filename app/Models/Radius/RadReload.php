<?php

namespace App\Models\Radius;

use Illuminate\Database\Eloquent\Model;

class RadReload extends Model
{
    protected $connection = 'radius';
    protected $table = 'nasreload';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nasipaddress',
        'reloadtime',
    ];
}
