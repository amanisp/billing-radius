<?php

namespace App\Services;

use App\Models\GlobalSettings;
use App\Models\Groups;
use App\Models\WhatsappTemplates;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsappCoreService
{
    private $baseUrl;
    private $verifySsl;

    public function __construct()
    {
        $this->baseUrl = config('services.core_whatsapp.url', 'http://localhost:3001');
        // Otomatis matikan verifikasi SSL jika jalan di lokal (bebas dari cURL error 60)
        $this->verifySsl = app()->environment('local') ? false : true;
    }

    /**
     * ✅ HELPER BARU: Membuat HTTP Client yang dinamis (mengurus SSL & Timeout)
     */
    private function httpClient(int $timeout = 5)
    {
        $client = Http::timeout($timeout);
        
        if (!$this->verifySsl) {
            $client->withoutVerifying();
        }

        return $client;
    }

    private function deviceExists(string $token): bool
    {
        try {
            // Gunakan helper HTTP dan urlencode
            $res = $this->httpClient()->get($this->baseUrl . '/devices/' . urlencode($token));

            if (!$res->successful()) return false;

            $data = $res->json();

            $state = $data['results']['status'] ?? $data['results']['state'] ?? null;

            return !empty($data['results']) && $state === 'logged_in';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function ensureDeviceByGroup(int $groupId): string
    {
        $group = Groups::findOrFail($groupId);
        return $this->ensureDevice($group);
    }

    public function ensureDevice(Groups $group): string
    {
        if (empty($group->wa_api_token) || $group->wa_api_token === 'default') {
            $token = $this->generateToken();
            $group->wa_api_token = $token;
            $group->save();
            $this->registerDevice($token);
        } else {
            $token = $group->wa_api_token;

            if (!$this->deviceExists($token)) {
                $this->deleteDevice($token);
                $this->registerDevice($token);
            }
        }

        return $token;
    }

    protected function generateToken(): string
    {
        return 'grp-' . Str::uuid();
    }

    protected function registerDevice(string $deviceId): void
    {
        try {
            $this->httpClient()->post($this->baseUrl . '/devices', [
                'device_id' => $deviceId
            ]);
        } catch (\Exception $e) {
            Log::error('Failed register device: ' . $e->getMessage());
        }
    }

    protected function deleteDevice(string $deviceId): void
    {
        try {
            $this->httpClient()->delete($this->baseUrl . '/devices/' . urlencode($deviceId));
        } catch (\Exception $e) {
            Log::warning('Failed delete device (ignored): ' . $e->getMessage());
        }
    }

    public function login(string $deviceId)
    {
        return $this->httpClient()
            ->withHeaders(['X-Device-Id' => $deviceId])
            ->get($this->baseUrl . '/app/login');
    }

    public function sendMessage(int $groupId, string $deviceId, array $payload)
    {
        $rawPhone = $payload['phone'] ?? null;
        $message  = $payload['message'] ?? null;
        $recipient = null;

        // 1. PENGAMAN NOMOR HP
        if ($rawPhone) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
            
            // ✅ FIX: Konversi awalan '0' menjadi '62'
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '62' . substr($cleanPhone, 1);
            }

            $recipient = $cleanPhone . '@s.whatsapp.net';
            $payload['phone'] = $recipient;
        }

        if (empty($message) || empty($recipient)) {
            Log::warning('Skip SendMessage: Pesan atau nomor tujuan kosong.', [
                'group_id' => $groupId,
                'phone'    => $recipient,
                'message'  => $message
            ]);

            return [
                'success' => false,
                'message' => 'Pesan atau nomor telepon tidak boleh kosong.'
            ];
        }

        $log = \App\Models\WhatsappMessageLog::create([
            'group_id'  => $groupId,
            'recipient' => $recipient,
            'message'   => $message,
            'status'    => 'queued',
            'type'      => 'text',
        ]);

        try {
            Log::info('DEBUG PAYLOAD WA (SEBELUM KIRIM):', $payload);
            
            // Gunakan helper HTTP dengan timeout 15
            $response = $this->httpClient(15)
                ->withHeaders(['X-Device-Id' => $deviceId])
                ->post($this->baseUrl . '/send/message', $payload);

            if (!$response->successful()) {
                $responseData = $response->json() ?? $response->body();
                $body = is_array($responseData) ? json_encode($responseData) : $responseData;
                
                if (str_contains($body, '463')) {
                    Log::warning('WhatsApp rate limit (463) detected, skip retry', [
                        'device_id' => $deviceId,
                        'recipient' => $recipient,
                    ]);
                }

                $log->update([
                    'status'        => 'failed',
                    'sent_at'       => now(),
                    'response_data' => $responseData,
                ]);

                Log::error('Send message failed', [
                    'device_id' => $deviceId,
                    'response'  => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to send message',
                    'error'   => $response->body(),
                ];
            }

            $log->update([
                'status'        => 'sent',
                'sent_at'       => now(),
                'response_data' => $response->json(),
            ]);

            return [
                'success' => true,
                'data'    => $response->json(),
            ];
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'sent_at'       => now(),
                'response_data' => $e->getMessage(),
            ]);

            Log::error('Send message exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Exception error',
                'error'   => $e->getMessage(),
            ];
        }
    }

    public function buildMessage(array $data): string
    {
        // Kode buildMessage ini sudah sempurna, tidak ada yang saya ubah
        if (!empty($data['message'])) {
            return $data['message'];
        }

        $templateKey = $data['template'] ?? null;
        $variables   = $data['variables'] ?? [];
        $groupId     = $data['group_id'] ?? null;

        if (!$templateKey) {
            return '';
        }

        $template = WhatsappTemplates::where('template_type', $templateKey)
            ->where(function ($query) use ($groupId) {
                if ($groupId) {
                    $query->where('group_id', $groupId)
                        ->orWhereNull('group_id');
                } else {
                    $query->whereNull('group_id');
                }
            })
            ->orderByRaw('group_id IS NULL')
            ->first();

        if (!$template) {
            return '';
        }

        $message = $template->content;

        if (str_contains($message, '[footer]')) {
            $setting = GlobalSettings::where('group_id', $groupId)->first();
            $footer = $setting?->footer ?? '';
            $message = str_replace('[footer]', $footer, $message);
        }

        foreach ($variables as $key => $value) {
            $message = str_replace("[$key]", (string) $value, $message);
        }

        return $message;
    }

    public function getDeviceStatusWithAvatar(string $deviceId, ?string $phone = null)
    {
        try {
            $statusResponse = $this->httpClient()->get($this->baseUrl . '/devices/' . urlencode($deviceId));

            $statusData = $statusResponse->successful() ? $statusResponse->json() : null;

            if (!$phone && isset($statusData['results']['jid'])) {
                $phone = $statusData['results']['jid'];
            }

            $avatarData = null;

            if ($phone) {
                $avatarResponse = $this->httpClient()
                    ->withHeaders(['X-Device-Id' => $deviceId])
                    ->get($this->baseUrl . '/user/avatar', [
                        'phone'        => $phone,
                        'is_preview'   => true,
                        'is_community' => false,
                    ]);

                if ($avatarResponse->successful()) {
                    $avatarData = $avatarResponse->json();
                }
            }

            return [
                'success' => true,
                'status'  => $statusData,
                'avatar'  => $avatarData,
                'phone'   => $phone,
            ];
        } catch (\Throwable $e) {
            Log::error('Get device status failed', [
                'error'     => $e->getMessage(),
                'device_id' => $deviceId,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fetch status',
                'error'   => $e->getMessage(),
            ];
        }
    }

    public function disconnectDevice(string $deviceId)
    {
        try {
            $encodedDeviceId = urlencode($deviceId);

            $this->httpClient()
                ->withHeaders(['accept' => 'application/json'])
                ->post($this->baseUrl . '/devices/' . $encodedDeviceId . '/logout');

            $response = $this->httpClient(10)
                ->withHeaders(['accept' => 'application/json'])
                ->delete($this->baseUrl . '/devices/' . $encodedDeviceId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Device disconnected successfully',
                    'data'    => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to disconnect device',
                'status'  => $response->status(),
                'error'   => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::error('Disconnect device failed', [
                'error'     => $e->getMessage(),
                'device_id' => $deviceId,
            ]);

            return [
                'success' => false,
                'message' => 'Exception occurred during disconnect',
                'error'   => $e->getMessage(),
            ];
        }
    }
}