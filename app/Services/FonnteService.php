<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Groups;
use App\Models\GlobalSettings;

class FonnteService
{
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.fonnte.url', 'https://api.fonnte.com');
    }

    private function renderTemplate(string $templateKey, array $variables = []): string
    {
        $templates = config('whatsapp_templates');

        if (!isset($templates[$templateKey])) {
            throw new \InvalidArgumentException("WhatsApp template '{$templateKey}' not found");
        }

        $message = $templates[$templateKey];

        foreach ($variables as $key => $value) {
            $message = str_replace(
                '[' . $key . ']',
                (string) $value,
                $message
            );
        }

        return $message;
    }


    private function getAccountToken($groupId = null): ?string
    {
        // 1. Config fallback
        if ($token = config('services.fonnte.account_token')) return $token;

        // 2. DB per group
        if ($groupId) {
            $settings = GlobalSettings::where('group_id', $groupId)->first();
            return $settings?->whatsapp_api_key;
        }
        return null;
    }

    private function getDeviceToken($groupId): ?string
    {
        $group = Groups::find($groupId);
        return $group?->wa_api_token ?? config('services.fonnte.device_token');
    }

    private function getCurrentGroupId(): ?int
    {
        return Auth::check() ? Auth::user()->group_id : null;
    }

    public function getDeviceStatus($groupId = null): array
    {
        $groupId = $groupId ?? $this->getCurrentGroupId();
        $accountToken = $this->getAccountToken($groupId);

        if (!$accountToken) {
            return ['success' => false, 'error' => 'Account token not configured (.env FONNTE_ACCOUNT_TOKEN or global_settings.whatsapp_api_key)'];
        }

        try {
            $response = Http::withHeaders(['Authorization' => $accountToken])
                ->timeout(30)
                ->post("{$this->baseUrl}/get-devices");

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            return $this->handleException($e, 'get_device_status');
        }
    }

    public function sendText(
        $groupId,
        string $target,
        array $data = [],   // isi message / template di sini
        array $options = []
    ): array {
        $groupId = $groupId ?? $this->getCurrentGroupId();
        $deviceToken = $this->getDeviceToken($groupId);

        if (!$deviceToken) {
            return [
                'success' => false,
                'error' => 'Device token not configured (scan QR & save to groups.wa_api_token)'
            ];
        }

        try {
            /**
             * PRIORITAS MESSAGE
             * 1. Custom message langsung
             * 2. Template message
             */
            if (!empty($data['message'])) {
                $message = $data['message'];
            } elseif (!empty($data['template'])) {
                $message = $this->renderTemplate(
                    $data['template'],
                    $data['variables'] ?? []
                );
            } else {
                return [
                    'success' => false,
                    'error' => 'Message or template is required'
                ];
            }

            $payload = [
                'target'  => $this->formatPhone($target),
                'message' => $message,
                'device'  => $options['device'] ?? null,
            ];

            if (isset($options['footer'])) {
                $payload['footer'] = $options['footer'];
            }

            if (isset($options['delay'])) {
                $payload['delay'] = (int) $options['delay'];
            }

            $response = Http::withHeaders([
                'Authorization' => $deviceToken
            ])
                ->timeout(30)
                ->post("{$this->baseUrl}/send", $payload);

            return $this->handleResponse($response);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'send_text');
        }
    }


    public function sendBroadcast(
        $groupId,
        array $targets,
        ?string $message = null,
        ?string $templateKey = null,
        array $variables = [],
        int $minDelay = 2,
        int $maxDelay = 10
    ): array {
        $groupId = $groupId ?? $this->getCurrentGroupId();
        $deviceToken = $this->getDeviceToken($groupId);

        if (!$deviceToken) {
            return ['success' => false, 'error' => 'Device token not configured'];
        }

        // Jika templateKey diberikan, render pesan dari template
        if ($templateKey) {
            $message = $this->renderTemplate($templateKey, $variables);
        }

        if (!$message) {
            return ['success' => false, 'error' => 'No message provided'];
        }

        $results = [];
        foreach ($targets as $index => $target) {
            // RANDOM DELAY 2-10 detik
            $randomDelay = rand($minDelay, $maxDelay);

            $payload = [
                'target' => $this->formatPhone($target),
                'message' => $message,
            ];

            // Kirim request ke Fonnte
            $response = Http::withHeaders(['Authorization' => $deviceToken])
                ->timeout(30)
                ->post("{$this->baseUrl}/send", $payload);

            $result = $this->handleResponse($response);
            $results[] = [
                'target' => $target,
                'local_delay_used' => "{$randomDelay}s",
                'success' => $result['success'],
                'data' => $result['data'] ?? null
            ];

            // LOCAL SLEEP untuk anti spam
            if ($index < count($targets) - 1) {
                sleep($randomDelay);
            }
        }

        return $results;
    }



    public function getQR($groupId, ?string $device = null, bool $autoread = true): array
    {
        $groupId = $groupId ?? $this->getCurrentGroupId();
        $token = $this->getDeviceToken($groupId) ?? $this->getAccountToken($groupId);

        if (!$token) {
            return ['success' => false, 'error' => 'Token not configured'];
        }

        try {
            $payload = ['autoread' => $autoread];
            if ($device) $payload['device'] = $this->formatPhone($device);

            $response = Http::withHeaders(['Authorization' => $token])
                ->timeout(30)
                ->post("{$this->baseUrl}/qr", $payload);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            return $this->handleException($e, 'get_qr');
        }
    }

    public function disconnect($groupId, ?string $device = null): array
    {
        $groupId = $groupId ?? $this->getCurrentGroupId();
        $deviceToken = $this->getDeviceToken($groupId);

        if (!$deviceToken) {
            return ['success' => false, 'error' => 'Device token not configured'];
        }

        try {
            $payload = [];
            if ($device) $payload['device'] = $this->formatPhone($device);

            $response = Http::withHeaders(['Authorization' => $deviceToken])
                ->timeout(30)
                ->post("{$this->baseUrl}/disconnect", $payload);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            return $this->handleException($e, 'disconnect');
        }
    }

    public function getDevices($groupId = null): array
    {
        $groupId = $groupId ?? $this->getCurrentGroupId();
        return $this->getDeviceStatus($groupId);
    }

    public static function getTemplates(): array
    {
        return [
            'invoice' => "Dear [username],\n\nInvoice #[no_invoice] [amount] due [due_date]\nService: [service_name] [bandwidth]\n\nPay now!\n[company_name]",
            'welcome' => "Welcome [username]!\n\nService [service_name] [bandwidth] active.\n\n[company_name]",
            'reminder' => "Reminder [username]!\n\nInvoice #[no_invoice] [amount] overdue.\n\nPay immediately.\n[company_name]",
            'suspension' => "[username], service suspended.\nUnpaid #[no_invoice] [amount].\nPay to reactivate.\n[company_name]",
            'reactivation' => "Service reactivated [username]!\nThank you.\n[company_name]",
        ];
    }

    public static function getTemplate(string $key): ?string
    {
        return self::getTemplates()[$key] ?? null;
    }

    public function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') return '62' . substr($phone, 1);
        if (substr($phone, 0, 2) !== '62') return '62' . $phone;
        return $phone;
    }

    private function handleResponse($response): array
    {
        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $response->json(),
                'status' => $response->status()
            ];
        }

        $error = $response->json('reason', 'API request failed');
        Log::error('Fonnte API Error', ['status' => $response->status(), 'error' => $error]);
        return ['success' => false, 'error' => $error, 'status' => $response->status()];
    }

    private function handleException(\Exception $e, string $action): array
    {
        Log::error("Fonnte {$action}", ['error' => $e->getMessage()]);
        return ['success' => false, 'error' => $e->getMessage(), 'status' => 500];
    }
}
