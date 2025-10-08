<?php

namespace App\Jobs;

use App\Models\WhatsappMessageLog;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $apiKey;
    protected $broadcast;
    protected $apiHost;


    public function __construct($apiKey, WhatsappMessageLog $broadcast)
    {
        $this->apiKey = $apiKey;
        $this->broadcast = $broadcast;
        $this->apiHost = env('WA_API_HOST');
    }

    public function handle()
    {

        try {
            $messageTemplate = "*{$this->broadcast->subject}*\n\n{$this->broadcast->message}";

            $response = Http::post("{$this->apiHost}api/send-message", [
                'api_key'  => $this->apiKey,
                'receiver' => $this->broadcast->phone,
                'data'     => [
                    'message' => $messageTemplate,
                ],
            ]);

            $responseBody = $response->body();
            $responseData = json_decode($responseBody, true);


            if (!empty($responseData) && isset($responseData['status']) && $responseData['status'] === true) {
                // Berhasil
                $this->broadcast->update([
                    'status'   => 'sent',
                    'response' => $responseBody,
                ]);
                Log::info("Message sent to {$this->broadcast->phone}");
            } else {
                // Gagal
                $this->broadcast->update([
                    'status'   => 'failed',
                    'response' => $responseBody,
                ]);
                Log::error("Failed to send message", [
                    'phone'    => $this->broadcast->phone,
                    'response' => $responseBody,
                ]);
            }
        } catch (\Exception $e) {
            $this->broadcast->update([
                'status' => 'failed',
                'response' => $e->getMessage()
            ]);
            Log::error("Exception sending message", [
                'phone' => $this->broadcast->phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
