<?php

namespace App\Jobs;

use App\Models\WhatsappMessageLog;
use App\Services\WhatsappCoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\WhatsappRateLimiter;
use Throwable;

class SendWhatsappBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 90;
    public $tries   = 5;

    /**
     * Backoff bertingkat: retry ke-1 setelah 30s, ke-2 setelah 60s, dst.
     */
    public function backoff(): array
    {
        return [30, 60, 120, 180, 300];
    }

    public function __construct(
        public int    $groupId,
        public string $target,
        public array  $data,
        public ?int   $logId = null
    ) {}

    public function handle(WhatsappCoreService $wa, WhatsappRateLimiter $rateLimiter): void
    {
        if (!$rateLimiter->hit($this->groupId)) {
            // LIMIT TERCAPAI (Sudah 5 pesan). 
            // Paksa sistem untuk JEDA 1 MENIT (60 detik) sebelum memproses pesan ini.
            $this->release(60);
            return;
        }

        try {
            $deviceId = $wa->ensureDeviceByGroup($this->groupId);

            $message = $wa->buildMessage($this->data);

            if (empty($message)) {
                Log::warning('Empty message, skip send', ['target' => $this->target]);
                return;
            }

            $payload = [
                'phone'   => $this->target,
                'message' => $message,
            ];

            $result = $wa->sendMessage($this->groupId, $deviceId, $payload);

            if ($this->logId) {
                WhatsappMessageLog::where('id', $this->logId)->update([
                    'status'        => $result['success'] ? 'sent' : 'failed',
                    'sent_at'       => now(),
                    'response_data' => json_encode($result),
                ]);
            }

            if (!$result['success']) {
                $errorBody = json_encode($result['error'] ?? '');

                // Deteksi rate limit / banned dari WA
                if ($this->isRateLimitError($errorBody)) {
                    Log::warning('WA rate limit hit, releasing job', [
                        'target' => $this->target,
                        'group'  => $this->groupId,
                    ]);
                    // Tunggu lebih lama jika kena rate limit WA
                    $this->release(rand(120, 300));
                    return;
                }

                Log::error('WA send failed', [
                    'target' => $this->target,
                    'error'  => $errorBody,
                ]);

                // Lempar exception agar masuk retry/backoff
                throw new \RuntimeException("WA send failed: {$errorBody}");
            }
        } catch (Throwable $e) {
            if ($this->logId) {
                WhatsappMessageLog::where('id', $this->logId)->update([
                    'status'        => 'failed',
                    'response_data' => json_encode(['error' => $e->getMessage()]),
                ]);
            }

            Log::error('Broadcast job exception', [
                'target'    => $this->target,
                'exception' => $e->getMessage(),
            ]);

            throw $e; // Agar Laravel handle retry/failed_jobs
        }
    }

    /**
     * Deteksi error rate limit / spam dari respons WA
     */
    private function isRateLimitError(string $body): bool
    {
        $patterns = ['463', '429', 'rate limit', 'spam', 'blocked', 'banned'];
        foreach ($patterns as $pattern) {
            if (stripos($body, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
}
