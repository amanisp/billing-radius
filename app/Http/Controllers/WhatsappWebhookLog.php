<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\GlobalSettings;
use App\Models\WhatsappWebhookLog;

class WhatsappWebhookController extends Controller
{
    /**
     * Handle webhook dari Wisender
     */
    public function handleWebhook(Request $request, $groupId = null)
    {
        try {
            $payload = $request->all();

            Log::info('WhatsApp Webhook received', [
                'group_id' => $groupId,
                'payload' => $payload
            ]);

            // Validasi API key jika diperlukan
            if ($groupId) {
                $apiKey = GlobalSettings::getWhatsAppApiKey($groupId);
                $authHeader = $request->header('Authorization');

                if (!$apiKey || !$authHeader || $authHeader !== 'Bearer ' . $apiKey) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }
            }

            // Handle different webhook types
            $webhookType = $payload['type'] ?? 'unknown';

            switch ($webhookType) {
                case 'status':
                    $this->handleStatusWebhook($groupId, $payload);
                    break;

                case 'message':
                    $this->handleMessageWebhook($groupId, $payload);
                    break;

                case 'device_info':
                    $this->handleDeviceWebhook($groupId, $payload);
                    break;

                default:
                    // Handle generic webhook
                    $this->handleGenericWebhook($groupId, $payload);
                    break;
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Webhook handling error', [
                'group_id' => $groupId,
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle status update webhook
     */
    private function handleStatusWebhook($groupId, $payload)
    {
        $statusData = [
            'status' => $payload['status'] ?? 'offline',
            'phone_number' => $payload['phone_number'] ?? null,
            'last_seen' => now()->toISOString(),
            'session_name' => $payload['session_name'] ?? null
        ];

        // Update cache
        Cache::put("whatsapp_status_{$groupId}", $statusData, 3600); // 1 hour

        // Optional: Save to database
        WhatsappWebhookLog::updateOrCreate(
            ['group_id' => $groupId],
            [
                'phone_number' => $statusData['phone_number'],
                'status' => $statusData['status'],
                'last_updated' => now()
            ]
        );

        Log::info('WhatsApp status updated', [
            'group_id' => $groupId,
            'status' => $statusData['status'],
            'phone' => $statusData['phone_number']
        ]);
    }

    /**
     * Handle message webhook (sent/received/failed)
     */
    private function handleMessageWebhook($groupId, $payload)
    {
        $messageData = [
            'message_id' => $payload['message_id'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'message' => $payload['message'] ?? null,
            'direction' => $payload['direction'] ?? 'outgoing', // incoming/outgoing
            'status' => $payload['status'] ?? 'unknown', // sent/delivered/read/failed
            'timestamp' => $payload['timestamp'] ?? now()->toISOString(),
            'error' => $payload['error'] ?? null
        ];

        // Get existing messages dari cache
        $messages = Cache::get("whatsapp_messages_{$groupId}", []);

        // Add new message ke awal array
        array_unshift($messages, $messageData);

        // Keep only last 100 messages
        $messages = array_slice($messages, 0, 100);

        // Update cache
        Cache::put("whatsapp_messages_{$groupId}", $messages, 3600);

        // Update message count in status
        $statusData = Cache::get("whatsapp_status_{$groupId}", []);
        $statusData['message_count'] = count($messages);
        Cache::put("whatsapp_status_{$groupId}", $statusData, 3600);

        Log::info('WhatsApp message logged', [
            'group_id' => $groupId,
            'message_id' => $messageData['message_id'],
            'phone' => $messageData['phone'],
            'status' => $messageData['status']
        ]);
    }

    /**
     * Handle device info webhook
     */
    private function handleDeviceWebhook($groupId, $payload)
    {
        $deviceInfo = [
            'device_name' => $payload['device_name'] ?? null,
            'device_model' => $payload['device_model'] ?? null,
            'whatsapp_version' => $payload['whatsapp_version'] ?? null,
            'battery_level' => $payload['battery_level'] ?? null,
            'updated_at' => now()->toISOString()
        ];

        // Update device info in status cache
        $statusData = Cache::get("whatsapp_status_{$groupId}", []);
        $statusData['device_info'] = $deviceInfo;
        Cache::put("whatsapp_status_{$groupId}", $statusData, 3600);

        // Optional: Save to database
        WhatsappWebhookLog::updateOrCreate(
            ['group_id' => $groupId],
            [
                'device_info' => $deviceInfo,
                'last_updated' => now()
            ]
        );

        Log::info('WhatsApp device info updated', [
            'group_id' => $groupId,
            'device' => $deviceInfo
        ]);
    }

    /**
     * Handle generic webhook (auto-detect type from payload)
     */
    private function handleGenericWebhook($groupId, $payload)
    {
        // Auto-detect webhook type from payload structure
        if (isset($payload['status']) && isset($payload['phone_number'])) {
            $this->handleStatusWebhook($groupId, $payload);
        } elseif (isset($payload['message_id']) || isset($payload['phone'])) {
            $this->handleMessageWebhook($groupId, $payload);
        } elseif (isset($payload['device_name']) || isset($payload['device_info'])) {
            $this->handleDeviceWebhook($groupId, $payload);
        } else {
            // Log unknown webhook
            Log::warning('Unknown webhook type received', [
                'group_id' => $groupId,
                'payload' => $payload
            ]);
        }
    }
}
