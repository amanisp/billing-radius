<?php

namespace App\Jobs;

use App\Models\WhatsappMessageLog;
use App\Services\FonnteService;
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
        public int $groupId,
        public string $target,
        public array $data,       // ['message'=>'...', 'template'=>'...', 'variables'=>[...] ]
        public array $options = [] // ['delay'=>10, 'footer'=>'...']
    ) {}

    public function handle(FonnteService $whatsapp)
    {
        try {
            // Kirim pesan menggunakan FonnteService
            $result = $whatsapp->sendText(
                $this->groupId,
                $this->target,
                $this->data,
                $this->options
            );

            // Update log queued jika ada
            $log = WhatsappMessageLog::where('recipient', $this->target)
                ->where('status', 'queued')
                ->latest()
                ->first();

            if ($log) {
                $log->update([
                    'status'        => $result['success'] ? 'sent' : 'failed',
                    'sent_at'       => now(),
                    'message'       => $this->data['message'] ?? ($this->data['template'] ?? null),
                    'response_data' => json_encode($result),
                ]);
            }

            // Log error jika gagal
            if (!$result['success']) {
                Log::error('Broadcast failed', [
                    'target' => $this->target,
                    'error'  => $result['error'] ?? 'Unknown error',
                    'data'   => $this->data,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Broadcast job exception', [
                'target' => $this->target,
                'exception' => $e->getMessage(),
                'data' => $this->data,
            ]);
        }
    }
}
