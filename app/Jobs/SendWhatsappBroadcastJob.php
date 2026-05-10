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

    public $timeout = 60; // 1 menit
    public $tries = 3;    // Maksimal percobaan ulang jika gagal (opsional)

    public function __construct(
        public int $groupId,
        public string $target,
        public array $data,
        public ?int $logId = null // Ambil ID log spesifik dari controller
    ) {}

    public function handle(WhatsappCoreService $wa, WhatsappRateLimiter $rateLimiter)
    {
        try {
            // 🔒 GLOBAL RATE LIMITER
            // Jika limit tercapai, kembalikan ke antrean dengan delay 10 detik
            // (Asumsi $rateLimiter->hit() me-return boolean)
            if (!$rateLimiter->hit()) {
                $this->release(10);
                return;
            }

            // HAPUS sleep()! 
            // Delay sudah diatur via ->delay() saat dispatch di Controller

            $deviceId = $wa->ensureDeviceByGroup($this->groupId);

            $payload = [
                'phone'   => $this->target,
                'message' => $wa->buildMessage($this->data),
            ];

            $result = $wa->sendMessage(
                $this->groupId,
                $deviceId,
                $payload
            );

            // Update log secara presisi menggunakan ID
            if ($this->logId) {
                WhatsappMessageLog::where('id', $this->logId)->update([
                    'status'        => $result['success'] ? 'sent' : 'failed',
                    'sent_at'       => now(),
                    'response_data' => json_encode($result),
                ]);
            }

            // Jika gagal dari sisi API WA (bukan exception)
            if (!$result['success']) {
                Log::error('WhatsApp API response error', [
                    'target' => $this->target,
                    'error'  => $result['error'] ?? 'Unknown error',
                ]);

                // Jika ingin job ini diulang oleh Laravel saat gagal kirim, uncomment ini:
                // throw new \Exception("Gagal mengirim WA: " . json_encode($result));
            }
        } catch (Throwable $e) {
            // Update status log menjadi failed karena system error
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

            // LEMPAR KEMBALI EXCEPTION-NYA
            // Agar Laravel tahu job ini GAGAL dan bisa masuk ke tabel failed_jobs
            throw $e;
        }
    }
}
