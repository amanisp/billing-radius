<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    public function isBillable()
    {
        return $this->position === 'EOS';
    }
}
