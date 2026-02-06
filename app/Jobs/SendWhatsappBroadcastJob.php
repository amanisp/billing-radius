<?php

namespace App\Jobs;

use App\Models\WhatsappMessageLog;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;  // 1 menit per pesan

    public function __construct(
        public string $deviceToken,
        public string $target,
        public string $message,
        public ?\DateTime $delayUntil = null
    ) {}

    public function handle(WhatsappService $whatsapp)
    {
        $result = $whatsapp->sendText(
            $this->deviceToken,
            $this->target,
            $this->message
        );

        // Update log
        WhatsappMessageLog::where('recipient', $this->target)
            ->where('message', $this->message)
            ->where('status', 'queued')
            ->latest()
            ->first()?->update([
                'status'        => $result['success'] ? 'sent' : 'failed',
                'sent_at'       => now(),
                'response_data' => json_encode($result)
            ]);

        if (!$result['success']) {
            Log::error('Broadcast failed', [
                'target' => $this->target,
                'error'  => $result['error']
            ]);
        }
    }
}
