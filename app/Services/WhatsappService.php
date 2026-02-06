<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.fonnte.url', 'https://api.fonnte.com');
    }

    /**
     * Send Text Message - CORE METHOD
     * @param string $deviceToken - dari DB per-group
     */
    public function sendText(string $deviceToken, string $target, string $message, array $options = []): array
    {
        try {
            $payload = [
                'target'  => $this->formatPhone($target),
                'message' => $message,
            ];

            // Options
            if (isset($options['footer']))   $payload['footer']   = $options['footer'];
            if (isset($options['delay']))    $payload['delay']    = $options['delay'];
            if (isset($options['schedule'])) $payload['schedule'] = $options['schedule'];
            if (isset($options['device']))   $payload['device']   = $options['device'];

            $response = Http::withHeaders(['Authorization' => $deviceToken])
                ->timeout(30)
                ->post("{$this->baseUrl}/send", $payload);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Send Media
     */
    public function sendMedia(string $deviceToken, string $target, string $url, array $options = []): array
    {
        try {
            $payload = [
                'target' => $this->formatPhone($target),
                'url'    => $url,
            ];

            if (isset($options['caption']))  $payload['caption']  = $options['caption'];
            if (isset($options['filename'])) $payload['filename'] = $options['filename'];
            if (isset($options['footer']))   $payload['footer']   = $options['footer'];
            if (isset($options['delay']))    $payload['delay']    = $options['delay'];

            $response = Http::withHeaders(['Authorization' => $deviceToken])
                ->timeout(30)
                ->post("{$this->baseUrl}/send", $payload);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Validate Number
     */
    public function validateNumber(string $deviceToken, string $target): array
    {
        $payload = ['target' => $this->formatPhone($target)];

        $response = Http::withHeaders(['Authorization' => $deviceToken])
            ->post("{$this->baseUrl}/validate", $payload);

        return $this->handleResponse($response);
    }

    /**
     * Generate QR
     */
    public function generateQR(string $deviceToken, ?string $device = null): array
    {
        $payload = [];
        if ($device) $payload['device'] = $device;

        $response = Http::withHeaders(['Authorization' => $deviceToken])
            ->post("{$this->baseUrl}/qr", $payload);

        return $this->handleResponse($response);
    }

    /**
     * Get Devices (pakai account token)
     */
    public function getDevices(string $accountToken): array
    {
        $response = Http::withHeaders(['Authorization' => $accountToken])
            ->post("{$this->baseUrl}/get-devices");

        return $this->handleResponse($response);
    }

    /**
     * Format Phone (Indonesia)
     */
    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) $phone = '62' . substr($phone, 1);
        if (!str_starts_with($phone, '62')) $phone = '62' . $phone;
        return $phone;
    }

    private function handleResponse($response): array
    {
        if ($response->successful()) {
            $data = $response->json();
            return ['success' => true, 'data' => $data];
        }

        $error = $response->json();
        Log::error('Fonnte API Error', ['status' => $response->status(), 'error' => $error]);
        return ['success' => false, 'error' => $error['reason'] ?? 'API failed'];
    }

    private function handleError(\Exception $e): array
    {
        Log::error('Fonnte Service Error', ['error' => $e->getMessage()]);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
