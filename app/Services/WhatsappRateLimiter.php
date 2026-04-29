<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class WhatsappRateLimiter
{
    protected string $key = 'wa_rate_limit';

    public function hit()
    {
        $count = Cache::increment($this->key);

        if ($count === 1) {
            Cache::put($this->key, 1, 60); // reset 60 detik
        }

        // limit 15 message / menit
        if ($count > 15) {
            sleep(5);
        }
    }
}
