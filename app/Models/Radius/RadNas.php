<?php

namespace App\Models\Radius;

use Illuminate\Database\Eloquent\Model;

class RadNas extends Model
{
    protected $connection = 'radius';
    protected $table = 'nas';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nasname',
        'shortname',
        'type',
        'ports',
        'secret',
        'server',
        'community',
        'description',
        'group_id'
    ];
}
