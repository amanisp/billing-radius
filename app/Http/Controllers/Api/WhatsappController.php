<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Helpers\ResponseFormatter;

class WhatsappController extends Controller
{
    private $mpwaUrl;
    private $apiKey;

    public function __construct()
    {
        $this->mpwaUrl = config('services.mpwa.base_url');
        $this->apiKey = config('services.mpwa.api_key');
    }

    /**
     * Generate QR Code untuk connect WhatsApp
     */
    public function generateQR(Request $request)
    {
        $request->validate([
            'device' => 'required|string', // Nomor device (628xxx)
        ]);

        try {
            $response = Http::post("{$this->mpwaUrl}/generate-qr", [
                'api_key' => $this->apiKey,
                'device' => $request->device,
                'force' => true, // Auto-create device jika belum ada
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['qrcode'])) {
                ActivityLogController::logCreate([
                    'device' => $request->device,
                    'action' => 'generate_qr',
                    'status' => 'success'
                ], 'whatsapp_devices');
                
                return ResponseFormatter::success([
                    'qrcode' => $data['qrcode'], // Base64 image
                    'message' => $data['message'],
                    'device' => $request->device,
                ], 'QR Code berhasil di-generate. Silakan scan dari HP WhatsApp.');
            }

            ActivityLogController::logCreateF([
                'device' => $request->device,
                'action' => 'generate_qr',
                'error' => $data
            ], 'whatsapp_devices');
            return ResponseFormatter::error($data, 'Gagal generate QR Code');

        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'device' => $request->device,
                'action' => 'generate_qr',
                'error' => $e->getMessage()
            ], 'whatsapp_devices');
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    /**
     * Check status device
     */
    public function deviceInfo(Request $request)
    {
        $request->validate([
            'number' => 'required|string',
        ]);

        try {
            $response = Http::get("{$this->mpwaUrl}/info-devices", [
                'api_key' => $this->apiKey,
                'number' => $request->number,
            ]);

            ActivityLogController::logCreate([
                'number' => $request->number,
                'action' => 'device_info',
                'status' => 'success'
            ], 'whatsapp_devices');

            return ResponseFormatter::success(
                $response->json(),
                'Device info retrieved'
            );

        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'number' => $request->number,
                'action' => 'device_info',
                'error' => $e->getMessage()
            ], 'whatsapp_devices');
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    /**
     * Send Text Message
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'number' => 'required|string',
            'message' => 'required|string',
            'footer' => 'nullable|string',
        ]);

        try {
            $response = Http::post("{$this->mpwaUrl}/send-message", [
                'api_key' => $this->apiKey,
                'sender' => $request->sender,
                'number' => $request->number,
                'message' => $request->message,
                'footer' => $request->footer ?? '',
            ]);

            ActivityLogController::logCreate([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_message',
                'status' => 'success'
            ], 'whatsapp_messages');

            return ResponseFormatter::success(
                $response->json(),
                'Message sent successfully'
            );

        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_message',
                'error' => $e->getMessage()
            ], 'whatsapp_messages');
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    /**
     * Send Media (Image/Video/Document)
     */
    public function sendMedia(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'number' => 'required|string',
            'media_type' => 'required|in:image,video,audio,document',
            'url' => 'required|url',
            'caption' => 'nullable|string',
        ]);

        try {
            $response = Http::post("{$this->mpwaUrl}/send-media", [
                'api_key' => $this->apiKey,
                'sender' => $request->sender,
                'number' => $request->number,
                'media_type' => $request->media_type,
                'url' => $request->url,
                'caption' => $request->caption ?? '',
            ]);

            ActivityLogController::logCreate([
                'sender' => $request->sender,
                'number' => $request->number,
                'media_type' => $request->media_type,
                'action' => 'send_media',
                'status' => 'success'
            ], 'whatsapp_messages');

            return ResponseFormatter::success(
                $response->json(),
                'Media sent successfully'
            );

        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'media_type' => $request->media_type,
                'action' => 'send_media',
                'error' => $e->getMessage()
            ], 'whatsapp_messages');
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    /**
     * Check apakah nomor valid WhatsApp
     */
    public function checkNumber(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'number' => 'required|string',
        ]);

        try {
            $response = Http::post("{$this->mpwaUrl}/check-number", [
                'api_key' => $this->apiKey,
                'sender' => $request->sender,
                'number' => $request->number,
            ]);

            $data = $response->json();
            $exists = $data['msg']['exists'] ?? false;

            ActivityLogController::logCreate([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'check_number',
                'exists' => $exists,
                'status' => 'success'
            ], 'whatsapp_devices');

            return ResponseFormatter::success($data,
                $exists ? 'Number exists on WhatsApp' : 'Number not found'
            );

        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'check_number',
                'error' => $e->getMessage()
            ], 'whatsapp_devices');
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }
}
