<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    protected $baseUrl;
    protected $mpwaUrl;
    protected $mpwaApiKey;

    public function __construct()
    {
        $this->baseUrl = config('app.wa_api', env('WA_API_HOST'));
        $this->mpwaUrl = config('services.mpwa.base_url');
        $this->mpwaApiKey = config('services.mpwa.api_key');
    }

    public function sendFromTemplate($apiKey, $phone, $templateKey, $variables = [], $logParams = [])
    {
        // Note: WhatsappTemplate model not yet created
        // This method is a placeholder for future template support

        // For now, return error
        return [
            'success' => false,
            'error'   => "Template support not yet implemented",
            'status'  => 501,
        ];
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
        try {
            // Format phone
            $formattedPhone = $this->formatPhoneNumber($phone);

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

                return $result;
            }

            $errorData = $response->json();
            $result = [
                'success' => false,
                'error'   => $errorData['message'] ?? $errorData['error'] ?? 'Failed to send message',
                'status'  => $response->status(),
                'data'    => $errorData,
            ];

            return $result;
        } catch (\Exception $e) {
            Log::error('WhatsApp Send Message Error:', [
                'phone'  => $phone,
                'error'  => $e->getMessage(),
            ]);

            $result = [
                'success' => false,
                'error'   => 'Service error: ' . $e->getMessage(),
                'status'  => 500,
            ];

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

    // ===================== MPWA API ENDPOINTS =====================

    /**
     * Send Text Message via MPWA
     * POST /send-message
     */
    public function sendMpwaTextMessage($apiKey, $sender, $number, $message, $footer = null, $msgid = null, $full = 0)
    {
        try {
            $payload = [
                'api_key' => $apiKey,
                'sender' => $sender,
                'number' => $number,
                'message' => $message,
            ];

            if ($footer) $payload['footer'] = $footer;
            if ($msgid) $payload['msgid'] = $msgid;
            if ($full) $payload['full'] = $full;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/send-message", $payload);

            return $this->handleMpwaResponse($response, 'send_text');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'send_text');
        }
    }

    /**
     * Send Media via MPWA
     * POST /send-media
     */
    public function sendMpwaMedia($apiKey, $sender, $number, $mediaType, $url, $caption = null, $footer = null, $msgid = null, $full = 0)
    {
        try {
            $payload = [
                'api_key' => $apiKey,
                'sender' => $sender,
                'number' => $number,
                'media_type' => $mediaType,
                'url' => $url,
            ];

            if ($caption) $payload['caption'] = $caption;
            if ($footer) $payload['footer'] = $footer;
            if ($msgid) $payload['msgid'] = $msgid;
            if ($full) $payload['full'] = $full;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/send-media", $payload);

            return $this->handleMpwaResponse($response, 'send_media');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'send_media');
        }
    }

    /**
     * Send Sticker via MPWA
     * POST /send-sticker
     */
    public function sendMpwaSticker($apiKey, $sender, $number, $url, $msgid = null, $full = 0)
    {
        try {
            $payload = [
                'api_key' => $apiKey,
                'sender' => $sender,
                'number' => $number,
                'url' => $url,
            ];

            if ($msgid) $payload['msgid'] = $msgid;
            if ($full) $payload['full'] = $full;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/send-sticker", $payload);

            return $this->handleMpwaResponse($response, 'send_sticker');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'send_sticker');
        }
    }

    /**
     * Send Button Message via MPWA
     * POST /send-button
     */
    public function sendMpwaButton($apiKey, $sender, $number, $message, $buttons, $url = null, $footer = null)
    {
        try {
            $payload = [
                'api_key' => $apiKey,
                'sender' => $sender,
                'number' => $number,
                'message' => $message,
                'button' => $buttons,
            ];

            if ($url) $payload['url'] = $url;
            if ($footer) $payload['footer'] = $footer;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/send-button", $payload);

            return $this->handleMpwaResponse($response, 'send_button');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'send_button');
        }
    }

    /**
     * Send List Message via MPWA
     * POST /send-list
     */
    public function sendMpwaList($apiKey, $sender, $number, $message, $buttontext, $title, $sections, $name = null, $footer = null, $msgid = null, $full = 0)
    {
        try {
            $payload = [
                'api_key' => $apiKey,
                'sender' => $sender,
                'number' => $number,
                'message' => $message,
                'buttontext' => $buttontext,
                'title' => $title,
                'sections' => $sections,
            ];

            if ($name) $payload['name'] = $name;
            if ($footer) $payload['footer'] = $footer;
            if ($msgid) $payload['msgid'] = $msgid;
            if ($full) $payload['full'] = $full;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/send-list", $payload);

            return $this->handleMpwaResponse($response, 'send_list');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'send_list');
        }
    }

    /**
     * Send Poll Message via MPWA
     * POST /send-poll
     */
    public function sendMpwaPoll($apiKey, $sender, $number, $name, $options, $countable = '1', $msgid = null, $full = 0)
    {
        try {
            $payload = [
                'api_key' => $apiKey,
                'sender' => $sender,
                'number' => $number,
                'name' => $name,
                'option' => $options,
                'countable' => $countable,
            ];

            if ($msgid) $payload['msgid'] = $msgid;
            if ($full) $payload['full'] = $full;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/send-poll", $payload);

            return $this->handleMpwaResponse($response, 'send_poll');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'send_poll');
        }
    }

    /**
     * Send Location Message via MPWA
     * POST /send-location
     */
    public function sendMpwaLocation($apiKey, $sender, $number, $latitude, $longitude, $msgid = null, $full = 0)
    {
        try {
            $payload = [
                'api_key' => $apiKey,
                'sender' => $sender,
                'number' => $number,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];

            if ($msgid) $payload['msgid'] = $msgid;
            if ($full) $payload['full'] = $full;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/send-location", $payload);

            return $this->handleMpwaResponse($response, 'send_location');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'send_location');
        }
    }

    /**
     * Send VCard (Contact) Message via MPWA
     * POST /send-vcard
     */
    public function sendMpwaVCard($apiKey, $sender, $number, $name, $phone, $msgid = null, $full = 0)
    {
        try {
            $payload = [
                'api_key' => $apiKey,
                'sender' => $sender,
                'number' => $number,
                'name' => $name,
                'phone' => $phone,
            ];

            if ($msgid) $payload['msgid'] = $msgid;
            if ($full) $payload['full'] = $full;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/send-vcard", $payload);

            return $this->handleMpwaResponse($response, 'send_vcard');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'send_vcard');
        }
    }

    /**
     * Send Product Message via MPWA
     * POST /send-product
     */
    public function sendMpwaProduct($apiKey, $sender, $number, $url, $message = null, $msgid = null, $full = 0)
    {
        try {
            $payload = [
                'api_key' => $apiKey,
                'sender' => $sender,
                'number' => $number,
                'url' => $url,
            ];

            if ($message) $payload['message'] = $message;
            if ($msgid) $payload['msgid'] = $msgid;
            if ($full) $payload['full'] = $full;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/send-product", $payload);

            return $this->handleMpwaResponse($response, 'send_product');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'send_product');
        }
    }

    /**
     * Send Text to Channel via MPWA
     * POST /send-text-channel
     */
    public function sendMpwaTextChannel($apiKey, $sender, $url, $message, $footer = null, $full = 0)
    {
        try {
            $payload = [
                'api_key' => $apiKey,
                'sender' => $sender,
                'url' => $url,
                'message' => $message,
            ];

            if ($footer) $payload['footer'] = $footer;
            if ($full) $payload['full'] = $full;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/send-text-channel", $payload);

            return $this->handleMpwaResponse($response, 'send_text_channel');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'send_text_channel');
        }
    }

    /**
     * Generate QR Code via MPWA
     * POST /generate-qr
     */
    public function generateMpwaQR($device, $apiKey = null, $force = true)
    {
        try {
            $apiKey = $apiKey ?? $this->mpwaApiKey;

            $payload = [
                'api_key' => $apiKey,
                'device' => $device,
                'force' => $force ? 1 : 0,
            ];

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/generate-qr", $payload);

            return $this->handleMpwaResponse($response, 'generate_qr');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'generate_qr');
        }
    }

    /**
     * Get Device Info via MPWA
     * GET /info-devices
     */
    public function getMpwaDeviceInfo($number, $apiKey = null)
    {
        try {
            $apiKey = $apiKey ?? $this->mpwaApiKey;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->get("{$this->mpwaUrl}/info-devices", [
                    'api_key' => $apiKey,
                    'number' => $number,
                ]);

            return $this->handleMpwaResponse($response, 'device_info');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'device_info');
        }
    }

    /**
     * Check Number via MPWA
     * POST /check-number
     */
    public function checkMpwaNumber($sender, $number, $apiKey = null)
    {
        try {
            $apiKey = $apiKey ?? $this->mpwaApiKey;

            $payload = [
                'api_key' => $apiKey,
                'sender' => $sender,
                'number' => $number,
            ];

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/check-number", $payload);

            return $this->handleMpwaResponse($response, 'check_number');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'check_number');
        }
    }

    /**
     * Disconnect/Logout Device via MPWA
     * POST /logout-device
     */
    public function logoutMpwaDevice($sender, $apiKey = null)
    {
        try {
            $apiKey = $apiKey ?? $this->mpwaApiKey;

            $payload = [
                'api_key' => $apiKey,
                'sender' => $sender,
            ];

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/logout-device", $payload);

            return $this->handleMpwaResponse($response, 'logout_device');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'logout_device');
        }
    }

    /**
     * Delete Device via MPWA
     * POST /delete-device
     */
    public function deleteMpwaDevice($sender, $apiKey = null)
    {
        try {
            $apiKey = $apiKey ?? $this->mpwaApiKey;

            $payload = [
                'api_key' => $apiKey,
                'sender' => $sender,
            ];

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post("{$this->mpwaUrl}/delete-device", $payload);

            return $this->handleMpwaResponse($response, 'delete_device');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'delete_device');
        }
    }

    /**
     * Get User Info via MPWA
     * GET /info-user
     */
    public function getMpwaUserInfo($username, $apiKey = null)
    {
        try {
            $apiKey = $apiKey ?? $this->mpwaApiKey;

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->get("{$this->mpwaUrl}/info-user", [
                    'api_key' => $apiKey,
                    'username' => $username,
                ]);

            return $this->handleMpwaResponse($response, 'user_info');
        } catch (\Exception $e) {
            return $this->formatMpwaError($e, 'user_info');
        }
    }

    // ===================== HELPER METHODS =====================

    /**
     * Handle MPWA API Response
     */
    private function handleMpwaResponse($response, $action)
    {
        try {
            $data = $response->json();

            if ($response->successful()) {
                Log::info("MPWA API Success: {$action}", ['response' => $data]);
                return [
                    'success' => true,
                    'status' => $response->status(),
                    'data' => $data,
                ];
            }

            Log::warning("MPWA API Error: {$action}", ['response' => $data, 'status' => $response->status()]);
            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $data['msg'] ?? $data['message'] ?? 'Unknown error',
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error("MPWA API Parse Error: {$action}", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'status' => $response->status() ?? 500,
                'error' => 'Failed to parse response: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Format MPWA API Error
     */
    private function formatMpwaError($exception, $action)
    {
        Log::error("MPWA API Exception: {$action}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        return [
            'success' => false,
            'status' => 500,
            'error' => $exception->getMessage(),
        ];
    }
}
