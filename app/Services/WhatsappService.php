<?php

namespace App\Services;

use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    protected $baseUrl;
    protected $messageLogger;

    public function __construct()
    {
        $this->baseUrl = config('app.wa_api', env('WA_API_HOST'));
        $this->messageLogger = app(WhatsappMessageLogger::class);
    }

    // Create Token
    public function createSession($name)
    {
        // request ke API wa
        $response = Http::timeout(20)->post("{$this->baseUrl}/sessions", [
            'name' => $name
        ]);

        if ($response->failed()) {
            return [
                'ok' => false,
                'message' => 'Failed to connect to WhatsApp API',
                'error' => $response->body()
            ];
        }

        $data = $response->json();

        if (!($data['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $data['message'] ?? 'Unknown error'
            ];
        }

        return [
            'ok' => true,
            'sessionId' => $data['session']['sessionId'],
            'token'     => $data['session']['token'],
            'name'      => $data['session']['name']
        ];
    }


    // Show Session
    public function checkSession($session)
    {
        // request ke API wa
        $response = Http::timeout(20)->post("{$this->baseUrl}/sessions{$session}");

        if ($response->failed()) {
            return [
                'ok' => false,
                'message' => 'Failed to connect to WhatsApp API',
                'error' => $response->body()
            ];
        }

        $data = $response->json();

        if (!($data['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $data['message'] ?? 'Unknown error'
            ];
        }

        return $data;
    }

    public function sendFromTemplate($apiKey, $phone, $templateKey, $variables = [], $logParams = [])
    {
        // Ambil template dari database
        $template = WhatsappTemplate::where('template_type', $templateKey)
            ->where('group_id', $logParams['group_id'] ?? null)
            ->first();

        if (!$template) {
            return [
                'success' => false,
                'error'   => "Template '$templateKey' not found",
                'status'  => 404,
            ];
        }

        // Replace variabel dinamis di message & subject
        $message = $this->replaceVariables($template->content, $variables);
        $subject = $template->subject ? $this->replaceVariables($template->subject, $variables) : null;

        // Kirim menggunakan fungsi utama
        return $this->sendTextMessage($apiKey, $phone, $message, $subject, $logParams);
    }

    /**
     * Replace variables in text
     */
    private function replaceVariables($text, $variables)
    {
        foreach ($variables as $key => $value) {
            $text = str_replace("[$key]", $value, $text);
        }
        return $text;
    }


    public function sendTextMessage($apiKey, $phone, $message, $subject = null, $logParams = [])
    {
        $messageLogId = null;

        try {
            // Format phone
            $formattedPhone = $this->formatPhoneNumber($phone);

            // Log start
            $logStartResult = $this->messageLogger->logMessageStart([
                'group_id'       => $logParams['group_id'] ?? null,
                'phone'          => $formattedPhone,
                'subject'        => $subject,
                'message'        => $message,
                'message_type'   => $logParams['message_type'] ?? 'individual',
                'scheduled_at'   => $logParams['scheduled_at'] ?? null,
                'metadata'       => $logParams['metadata'] ?? null,
                'original_phone' => $phone,
                'api_key'        => $apiKey,
            ]);

            if ($logStartResult['success']) {
                $messageLogId = $logStartResult['message_log']->id;
            }

            // Payload
            $payload = [
                'api_key'  => $apiKey,
                'receiver' => $formattedPhone,
                'data'     => ['message' => $message],
            ];
            if ($subject) {
                $payload['data']['caption'] = $subject;
            }

            // Send API
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post($this->baseUrl . '/send-message', $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                $result = [
                    'success'    => true,
                    'data'       => $responseData,
                    'message_id' => $responseData['id'] ?? $responseData['message_id'] ?? null,
                ];

                if ($messageLogId) {
                    $this->messageLogger->logMessageResult($messageLogId, $result, $responseData);
                }

                return $result;
            }

            $errorData = $response->json();
            $result = [
                'success' => false,
                'error'   => $errorData['message'] ?? $errorData['error'] ?? 'Failed to send message',
                'status'  => $response->status(),
                'data'    => $errorData,
            ];

            if ($messageLogId) {
                $this->messageLogger->logMessageResult($messageLogId, $result, $errorData);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('WhatsApp Send Message Error:', [
                'phone'          => $phone,
                'error'          => $e->getMessage(),
                'message_log_id' => $messageLogId,
            ]);

            $result = [
                'success' => false,
                'error'   => 'Service error: ' . $e->getMessage(),
                'status'  => 500,
            ];

            if ($messageLogId) {
                $this->messageLogger->logMessageResult($messageLogId, $result);
            }

            return $result;
        }
    }
    /**
     * Test API key dengan Wisender.
     */
    public function testApiKey($apiKey)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])
                ->timeout(10)
                ->get($this->baseUrl . '/status');

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'data'    => [
                        'status'       => $data['status'] ?? 'ready',
                        'phone_number' => $data['phone'] ?? null,
                        'session_name' => $data['session'] ?? 'WhatsApp API',
                        'device_info'  => $data['device'] ?? null,
                        'qr_code'      => $data['qr'] ?? null,
                    ],
                ];
            }

            return [
                'success'  => false,
                'error'    => 'Invalid API key or service unavailable',
                'response' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp API Key Test Error:', [
                'error'   => $e->getMessage(),
                'api_key' => substr($apiKey, 0, 8) . '...',
            ]);

            return [
                'success' => false,
                'error'   => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate dan format nomor HP.
     */
    public function validatePhoneNumber($phone)
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 15) {
            return [
                'valid' => false,
                'error' => 'Phone number must be between 10-15 digits',
            ];
        }

        $formatted = $this->formatPhoneNumber($cleanPhone);

        return [
            'valid'     => true,
            'formatted' => $formatted,
            'original'  => $phone,
        ];
    }

    /**
     * Format nomor HP ke format internasional Indonesia.
     */
    private function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Jika dimulai dengan 0, ganti dengan 62
        if (substr($phone, 0, 1) === '0') {
            $phone = '62' . substr($phone, 1);
        }

        // Jika tidak dimulai dengan 62, tambahkan 62
        if (substr($phone, 0, 2) !== '62') {
            $phone = '62' . $phone;
        }

        return $phone;
    }
}
