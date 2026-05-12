<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class WhatsappRateLimiter
{
    /**
     * Key unik per group agar antar-group tidak saling blokir
     */
    protected function key(int $groupId): string
    {
        return "wa_rate_limit_group_{$groupId}";
    }

    /**
     * @return bool True jika diizinkan, False jika limit tercapai
     */
    public function hit(int $groupId = 0): bool
    {
        $key   = $this->key($groupId);
        $count = Cache::increment($key);

        if ($count === 1) {
            // Aktifkan jendela waktu selama 60 detik
            Cache::put($key, 1, now()->addSeconds(60));
        }

        // MAKSIMAL 5 PESAN
        return $count <= 5;
    }

    public function remaining(int $groupId = 0): int
    {
        $key   = $this->key($groupId);
        $count = Cache::get($key, 0);
        // Sesuaikan juga di sini menjadi 5
        return max(0, 5 - $count);
    }
}
