<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class WhatsappRateLimiter
{
    protected string $key = 'wa_rate_limit';

    /**
     * @return bool True jika diizinkan, False jika limit tercapai
     */
    public function hit(): bool
    {
        // Increment nilai cache
        $count = Cache::increment($this->key);

        // Jika ini adalah request pertama, atur waktu kedaluwarsa (TTL) menjadi 60 detik
        if ($count === 1) {
            // Gunakan addSeconds() atau set TTL yang jelas agar cache otomatis hilang dalam 1 menit
            Cache::put($this->key, 1, now()->addSeconds(60));
        }

        // Limit 15 message / menit
        if ($count > 15) {
            // JANGAN gunakan sleep(5) di sini karena akan memblokir worker.
            // Cukup return false agar Job tahu bahwa limit tercapai dan melakukan $this->release()
            return false;
        }

        return true;
    }
}
