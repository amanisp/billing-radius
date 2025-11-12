<?php

namespace App\Models\Radius;

use Illuminate\Database\Eloquent\Model;

class RadAcct extends Model
{
    protected $connection = 'radius';
    protected $table = 'radacct';
    protected $primaryKey = 'radacctid';
    public $timestamps = false;

    protected $fillable = [
        'acctsessionid',
        'acctuniqueid',
        'username',
        'realm',
        'nasipaddress',
        'nasportid',
        'nasporttype',
        'acctstarttime',
        'acctupdatetime',
        'acctstoptime',
        'acctinterval',
        'acctsessiontime',
        'acctauthentic',
        'connectinfo_start',
        'connectinfo_stop',
        'acctinputoctets',
        'acctoutputoctets',
        'calledstationid',
        'callingstationid',
        'acctterminatecause',
        'servicetype',
        'framedprotocol',
        'framedipaddress'
    ];

    public function userGroup()
    {
        return $this->hasOne(RadUserGroup::class, 'username', 'username');
    }
}
