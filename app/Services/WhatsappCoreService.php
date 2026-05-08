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

    public function __construct()
    {
        $this->baseUrl = config('services.core_whatsapp.url', 'http://localhost:3001');
    }

    private function deviceExists(string $token): bool
    {
        try {
            $res = Http::timeout(5)->get($this->baseUrl . '/devices/' . $token);

            if (!$res->successful()) return false;

            $data = $res->json();

            // ✅ cek juga apakah device statusnya logged_in
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
                // ✅ hapus device lama dulu sebelum register ulang
                $this->deleteDevice($token);
                $this->registerDevice($token);
            }
        }

        return $token;
    }

    protected function generateToken(): string
    {
        // ✅ pakai group prefix yang lebih deskriptif
        return 'grp-' . Str::uuid();
    }

    protected function registerDevice(string $deviceId): void
    {
        try {
            Http::timeout(5)->post($this->baseUrl . '/devices', [
                'device_id' => $deviceId
            ]);
        } catch (\Exception $e) {
            Log::error('Failed register device: ' . $e->getMessage());
        }
    }

    // ✅ method baru: hapus device dari WA service
    protected function deleteDevice(string $deviceId): void
    {
        try {
            Http::timeout(5)->delete($this->baseUrl . '/devices/' . $deviceId);
        } catch (\Exception $e) {
            Log::warning('Failed delete device (ignored): ' . $e->getMessage());
        }
    }

    public function login(string $deviceId)
    {
        return Http::withHeaders([
            'X-Device-Id' => $deviceId
        ])->get($this->baseUrl . '/app/login');
    }

    public function sendMessage(int $groupId, string $deviceId, array $payload)
    {
        $recipient = $payload['phone'] ?? null;
        $message   = $payload['message'] ?? null;

        $log = \App\Models\WhatsappMessageLog::create([
            'group_id'  => $groupId,
            'recipient' => $recipient,
            'message'   => $message,
            'status'    => 'queued',
            'type'      => 'text',
        ]);

        try {
            $response = Http::timeout(15)
                // ✅ hapus retry — jangan retry saat WA lagi rate limit
                ->withHeaders(['X-Device-Id' => $deviceId])
                ->post($this->baseUrl . '/send/message', $payload);

            if (!$response->successful()) {
                $responseData = $response->json() ?? $response->body();

                // ✅ deteksi error 463 secara eksplisit
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
        // jika message manual dikirim langsung
        if (!empty($data['message'])) {
            return $data['message'];
        }

        $templateKey = $data['template'] ?? null;
        $variables   = $data['variables'] ?? [];
        $groupId     = $data['group_id'] ?? null;

        if (!$templateKey) {
            return '';
        }

        // ambil template dari database
        $template = WhatsappTemplates::where(
            'template_type',
            $templateKey
        )
            ->where(function ($query) use ($groupId) {

                // prioritas template group
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

        /**
         * ambil footer dari global settings
         */
        if (
            str_contains($message, '[footer]')
        ) {

            $setting = GlobalSettings::where(
                'group_id',
                $groupId
            )->first();

            $footer = $setting?->footer ?? '';

            $message = str_replace(
                '[footer]',
                $footer,
                $message
            );
        }

        // replace variable lain
        foreach ($variables as $key => $value) {

            $message = str_replace(
                "[$key]",
                (string) $value,
                $message
            );
        }

        return $message;
    }

    public function getDeviceStatusWithAvatar(string $deviceId, ?string $phone = null)
    {
        try {
            $statusResponse = Http::timeout(5)
                ->get($this->baseUrl . '/devices/' . $deviceId);

            $statusData = $statusResponse->successful()
                ? $statusResponse->json()
                : null;

            if (!$phone && isset($statusData['results']['jid'])) {
                $phone = $statusData['results']['jid'];
            }

            $avatarData = null;

            if ($phone) {
                $avatarResponse = Http::timeout(5)
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
            // ✅ encode device ID untuk URL (handle karakter khusus)
            $encodedDeviceId = urlencode($deviceId);

            // ✅ logout dulu via endpoint v8
            Http::timeout(5)
                ->withHeaders(['accept' => 'application/json'])
                ->post($this->baseUrl . '/devices/' . $encodedDeviceId . '/logout');

            // ✅ delete device
            $response = Http::timeout(10)
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
