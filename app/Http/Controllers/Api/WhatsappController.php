<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Helpers\ResponseFormatter;

class WhatsappController extends Controller
{
    private $mpwaUrl;
    private $apiKey;
    private $whatsappService;

    public function __construct(WhatsappService $whatsappService)
    {
        $this->mpwaUrl = config('services.mpwa.base_url');
        $this->apiKey = config('services.mpwa.api_key');
        $this->whatsappService = $whatsappService;
    }

    // ===================== DEVICE MANAGEMENT =====================

    /**
     * Generate QR Code untuk connect WhatsApp
     * POST /whatsapp/generate-qr
     */
    public function generateQR(Request $request)
    {
        $request->validate([
            'device' => 'required|string',
        ]);

        try {
            $apiKey = $this->apiKey;
            $result = $this->whatsappService->generateMpwaQR(
                $request->device,
                $apiKey,
                true
            );

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'device' => $request->device,
                    'action' => 'generate_qr',
                    'status' => 'success'
                ], 'whatsapp_devices');

                return ResponseFormatter::success([
                    'qrcode' => $result['data']['qrcode'] ?? null,
                    'message' => $result['data']['message'] ?? 'QR Code generated',
                    'device' => $request->device,
                ], 'QR Code berhasil di-generate');
            }

            ActivityLogController::logCreateF([
                'device' => $request->device,
                'action' => 'generate_qr',
                'error' => $result['error']
            ], 'whatsapp_devices');

            return ResponseFormatter::error(null, $result['error']);
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
     * Get Device Information
     * GET /whatsapp/device-info
     */
    public function deviceInfo(Request $request)
    {
        $request->validate([
            'number' => 'required|string',
            'api_key' => 'nullable|string',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->getMpwaDeviceInfo($request->number, $apiKey);

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'number' => $request->number,
                    'action' => 'device_info',
                    'status' => 'success'
                ], 'whatsapp_devices');

                return ResponseFormatter::success(
                    $result['data'],
                    'Device info retrieved successfully'
                );
            }

            ActivityLogController::logCreateF([
                'number' => $request->number,
                'action' => 'device_info',
                'error' => $result['error']
            ], 'whatsapp_devices');

            return ResponseFormatter::error(null, $result['error']);
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
     * Disconnect/Logout Device
     * POST /whatsapp/logout-device
     */
    public function logoutDevice(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'api_key' => 'nullable|string',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->logoutMpwaDevice($request->sender, $apiKey);

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'sender' => $request->sender,
                    'action' => 'logout_device',
                    'status' => 'success'
                ], 'whatsapp_devices');

                return ResponseFormatter::success(null, 'Device disconnected successfully');
            }

            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'action' => 'logout_device',
                'error' => $result['error']
            ], 'whatsapp_devices');

            return ResponseFormatter::error(null, $result['error']);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'action' => 'logout_device',
                'error' => $e->getMessage()
            ], 'whatsapp_devices');
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    /**
     * Delete Device
     * POST /whatsapp/delete-device
     */
    public function deleteDevice(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'api_key' => 'nullable|string',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->deleteMpwaDevice($request->sender, $apiKey);

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'sender' => $request->sender,
                    'action' => 'delete_device',
                    'status' => 'success'
                ], 'whatsapp_devices');

                return ResponseFormatter::success(null, 'Device deleted successfully');
            }

            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'action' => 'delete_device',
                'error' => $result['error']
            ], 'whatsapp_devices');

            return ResponseFormatter::error(null, $result['error']);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'action' => 'delete_device',
                'error' => $e->getMessage()
            ], 'whatsapp_devices');
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }

    // ===================== MESSAGING =====================

    /**
     * Send Text Message
     * POST /whatsapp/send-message
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'number' => 'required|string',
            'message' => 'required|string',
            'footer' => 'nullable|string',
            'msgid' => 'nullable|string',
            'api_key' => 'nullable|string',
            'full' => 'nullable|boolean',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->sendMpwaTextMessage(
                $apiKey,
                $request->sender,
                $request->number,
                $request->message,
                $request->footer,
                $request->msgid,
                $request->full ? 1 : 0
            );

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'sender' => $request->sender,
                    'number' => $request->number,
                    'action' => 'send_message',
                    'status' => 'success'
                ], 'whatsapp_messages');

                return ResponseFormatter::success(
                    $result['data'],
                    'Message sent successfully'
                );
            }

            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_message',
                'error' => $result['error']
            ], 'whatsapp_messages');

            return ResponseFormatter::error(null, $result['error'], 500);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_message',
                'error' => $e->getMessage()
            ], 'whatsapp_messages');
            return ResponseFormatter::error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Send Media (Image, Video, Audio, Document)
     * POST /whatsapp/send-media
     */
    public function sendMedia(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'number' => 'required|string',
            'media_type' => 'required|in:image,video,audio,document',
            'url' => 'required|url',
            'caption' => 'nullable|string',
            'footer' => 'nullable|string',
            'msgid' => 'nullable|string',
            'api_key' => 'nullable|string',
            'full' => 'nullable|boolean',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->sendMpwaMedia(
                $apiKey,
                $request->sender,
                $request->number,
                $request->media_type,
                $request->url,
                $request->caption,
                $request->footer,
                $request->msgid,
                $request->full ? 1 : 0
            );

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'sender' => $request->sender,
                    'number' => $request->number,
                    'media_type' => $request->media_type,
                    'action' => 'send_media',
                    'status' => 'success'
                ], 'whatsapp_messages');

                return ResponseFormatter::success(
                    $result['data'],
                    'Media sent successfully'
                );
            }

            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'media_type' => $request->media_type,
                'action' => 'send_media',
                'error' => $result['error']
            ], 'whatsapp_messages');

            return ResponseFormatter::error(null, $result['error'], 500);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_media',
                'error' => $e->getMessage()
            ], 'whatsapp_messages');
            return ResponseFormatter::error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Send Sticker
     * POST /whatsapp/send-sticker
     */
    public function sendSticker(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'number' => 'required|string',
            'url' => 'required|url',
            'msgid' => 'nullable|string',
            'api_key' => 'nullable|string',
            'full' => 'nullable|boolean',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->sendMpwaSticker(
                $apiKey,
                $request->sender,
                $request->number,
                $request->url,
                $request->msgid,
                $request->full ? 1 : 0
            );

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'sender' => $request->sender,
                    'number' => $request->number,
                    'action' => 'send_sticker',
                    'status' => 'success'
                ], 'whatsapp_messages');

                return ResponseFormatter::success(
                    $result['data'],
                    'Sticker sent successfully'
                );
            }

            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_sticker',
                'error' => $result['error']
            ], 'whatsapp_messages');

            return ResponseFormatter::error(null, $result['error'], 500);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_sticker',
                'error' => $e->getMessage()
            ], 'whatsapp_messages');
            return ResponseFormatter::error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Send Button Message
     * POST /whatsapp/send-button
     */
    public function sendButton(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'number' => 'required|string',
            'message' => 'required|string',
            'button' => 'required|array|min:1',
            'button.*.type' => 'required|in:reply,call,url,copy',
            'button.*.displayText' => 'required|string',
            'url' => 'nullable|url',
            'footer' => 'nullable|string',
            'api_key' => 'nullable|string',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->sendMpwaButton(
                $apiKey,
                $request->sender,
                $request->number,
                $request->message,
                $request->button,
                $request->url,
                $request->footer
            );

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'sender' => $request->sender,
                    'number' => $request->number,
                    'action' => 'send_button',
                    'status' => 'success'
                ], 'whatsapp_messages');

                return ResponseFormatter::success(
                    $result['data'],
                    'Button message sent successfully'
                );
            }

            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_button',
                'error' => $result['error']
            ], 'whatsapp_messages');

            return ResponseFormatter::error(null, $result['error'], 500);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_button',
                'error' => $e->getMessage()
            ], 'whatsapp_messages');
            return ResponseFormatter::error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Send List Message
     * POST /whatsapp/send-list
     */
    public function sendList(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'number' => 'required|string',
            'message' => 'required|string',
            'buttontext' => 'required|string',
            'title' => 'required|string',
            'sections' => 'required|array|min:1',
            'sections.*.title' => 'required|string',
            'sections.*.rows' => 'required|array|min:1',
            'sections.*.rows.*.title' => 'required|string',
            'sections.*.rows.*.rowId' => 'required|string',
            'name' => 'nullable|string',
            'footer' => 'nullable|string',
            'msgid' => 'nullable|string',
            'api_key' => 'nullable|string',
            'full' => 'nullable|boolean',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->sendMpwaList(
                $apiKey,
                $request->sender,
                $request->number,
                $request->message,
                $request->buttontext,
                $request->title,
                $request->sections,
                $request->name,
                $request->footer,
                $request->msgid,
                $request->full ? 1 : 0
            );

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'sender' => $request->sender,
                    'number' => $request->number,
                    'action' => 'send_list',
                    'status' => 'success'
                ], 'whatsapp_messages');

                return ResponseFormatter::success(
                    $result['data'],
                    'List message sent successfully'
                );
            }

            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_list',
                'error' => $result['error']
            ], 'whatsapp_messages');

            return ResponseFormatter::error(null, $result['error'], 500);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_list',
                'error' => $e->getMessage()
            ], 'whatsapp_messages');
            return ResponseFormatter::error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Send Poll Message
     * POST /whatsapp/send-poll
     */
    public function sendPoll(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'number' => 'required|string',
            'name' => 'required|string',
            'option' => 'required|array|min:1',
            'countable' => 'nullable|in:0,1',
            'msgid' => 'nullable|string',
            'api_key' => 'nullable|string',
            'full' => 'nullable|boolean',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->sendMpwaPoll(
                $apiKey,
                $request->sender,
                $request->number,
                $request->name,
                $request->option,
                $request->countable ?? '1',
                $request->msgid,
                $request->full ? 1 : 0
            );

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'sender' => $request->sender,
                    'number' => $request->number,
                    'action' => 'send_poll',
                    'status' => 'success'
                ], 'whatsapp_messages');

                return ResponseFormatter::success(
                    $result['data'],
                    'Poll message sent successfully'
                );
            }

            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_poll',
                'error' => $result['error']
            ], 'whatsapp_messages');

            return ResponseFormatter::error(null, $result['error'], 500);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_poll',
                'error' => $e->getMessage()
            ], 'whatsapp_messages');
            return ResponseFormatter::error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Send Location
     * POST /whatsapp/send-location
     */
    public function sendLocation(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'number' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'msgid' => 'nullable|string',
            'api_key' => 'nullable|string',
            'full' => 'nullable|boolean',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->sendMpwaLocation(
                $apiKey,
                $request->sender,
                $request->number,
                $request->latitude,
                $request->longitude,
                $request->msgid,
                $request->full ? 1 : 0
            );

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'sender' => $request->sender,
                    'number' => $request->number,
                    'action' => 'send_location',
                    'status' => 'success'
                ], 'whatsapp_messages');

                return ResponseFormatter::success(
                    $result['data'],
                    'Location sent successfully'
                );
            }

            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_location',
                'error' => $result['error']
            ], 'whatsapp_messages');

            return ResponseFormatter::error(null, $result['error'], 500);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_location',
                'error' => $e->getMessage()
            ], 'whatsapp_messages');
            return ResponseFormatter::error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Send VCard (Contact)
     * POST /whatsapp/send-vcard
     */
    public function sendVCard(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'number' => 'required|string',
            'name' => 'required|string',
            'phone' => 'required|string',
            'msgid' => 'nullable|string',
            'api_key' => 'nullable|string',
            'full' => 'nullable|boolean',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->sendMpwaVCard(
                $apiKey,
                $request->sender,
                $request->number,
                $request->name,
                $request->phone,
                $request->msgid,
                $request->full ? 1 : 0
            );

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'sender' => $request->sender,
                    'number' => $request->number,
                    'action' => 'send_vcard',
                    'status' => 'success'
                ], 'whatsapp_messages');

                return ResponseFormatter::success(
                    $result['data'],
                    'Contact sent successfully'
                );
            }

            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_vcard',
                'error' => $result['error']
            ], 'whatsapp_messages');

            return ResponseFormatter::error(null, $result['error'], 500);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_vcard',
                'error' => $e->getMessage()
            ], 'whatsapp_messages');
            return ResponseFormatter::error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Send Product
     * POST /whatsapp/send-product
     */
    public function sendProduct(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'number' => 'required|string',
            'url' => 'required|url',
            'message' => 'nullable|string',
            'msgid' => 'nullable|string',
            'api_key' => 'nullable|string',
            'full' => 'nullable|boolean',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->sendMpwaProduct(
                $apiKey,
                $request->sender,
                $request->number,
                $request->url,
                $request->message,
                $request->msgid,
                $request->full ? 1 : 0
            );

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'sender' => $request->sender,
                    'number' => $request->number,
                    'action' => 'send_product',
                    'status' => 'success'
                ], 'whatsapp_messages');

                return ResponseFormatter::success(
                    $result['data'],
                    'Product sent successfully'
                );
            }

            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_product',
                'error' => $result['error']
            ], 'whatsapp_messages');

            return ResponseFormatter::error(null, $result['error'], 500);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'send_product',
                'error' => $e->getMessage()
            ], 'whatsapp_messages');
            return ResponseFormatter::error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Send Text to Channel
     * POST /whatsapp/send-text-channel
     */
    public function sendTextChannel(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'url' => 'required|url',
            'message' => 'required|string',
            'footer' => 'nullable|string',
            'api_key' => 'nullable|string',
            'full' => 'nullable|boolean',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->sendMpwaTextChannel(
                $apiKey,
                $request->sender,
                $request->url,
                $request->message,
                $request->footer,
                $request->full ? 1 : 0
            );

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'sender' => $request->sender,
                    'url' => $request->url,
                    'action' => 'send_text_channel',
                    'status' => 'success'
                ], 'whatsapp_messages');

                return ResponseFormatter::success(
                    $result['data'],
                    'Message sent to channel successfully'
                );
            }

            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'url' => $request->url,
                'action' => 'send_text_channel',
                'error' => $result['error']
            ], 'whatsapp_messages');

            return ResponseFormatter::error(null, $result['error'], 500);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'action' => 'send_text_channel',
                'error' => $e->getMessage()
            ], 'whatsapp_messages');
            return ResponseFormatter::error(null, $e->getMessage(), 500);
        }
    }

    // ===================== NUMBER & USER INFO =====================

    /**
     * Check if Number is WhatsApp User
     * POST /whatsapp/check-number
     */
    public function checkNumber(Request $request)
    {
        $request->validate([
            'sender' => 'required|string',
            'number' => 'required|string',
            'api_key' => 'nullable|string',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->checkMpwaNumber(
                $request->sender,
                $request->number,
                $apiKey
            );

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'sender' => $request->sender,
                    'number' => $request->number,
                    'action' => 'check_number',
                    'exists' => $result['data']['msg']['exists'] ?? false,
                    'status' => 'success'
                ], 'whatsapp_devices');

                return ResponseFormatter::success(
                    $result['data'],
                    'Number check completed'
                );
            }

            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'check_number',
                'error' => $result['error']
            ], 'whatsapp_devices');

            return ResponseFormatter::error(null, $result['error'], 500);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'sender' => $request->sender,
                'number' => $request->number,
                'action' => 'check_number',
                'error' => $e->getMessage()
            ], 'whatsapp_devices');
            return ResponseFormatter::error(null, $e->getMessage(), 500);
        }
    }

    /**
     * Get User Information
     * GET /whatsapp/user-info
     */
    public function getUserInfo(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'api_key' => 'nullable|string',
        ]);

        try {
            $apiKey = $request->api_key ?? $this->apiKey;
            $result = $this->whatsappService->getMpwaUserInfo($request->username, $apiKey);

            if ($result['success']) {
                ActivityLogController::logCreate([
                    'username' => $request->username,
                    'action' => 'user_info',
                    'status' => 'success'
                ], 'whatsapp_users');

                return ResponseFormatter::success(
                    $result['data'],
                    'User info retrieved successfully'
                );
            }

            ActivityLogController::logCreateF([
                'username' => $request->username,
                'action' => 'user_info',
                'error' => $result['error']
            ], 'whatsapp_users');

            return ResponseFormatter::error(null, $result['error'], 500);
        } catch (\Exception $e) {
            ActivityLogController::logCreateF([
                'username' => $request->username,
                'action' => 'user_info',
                'error' => $e->getMessage()
            ], 'whatsapp_users');
            return ResponseFormatter::error(null, $e->getMessage(), 500);
        }
    }

    // ===================== DEPRECATED - LEGACY METHODS =====================
    // Kept for backward compatibility

    /**
     * @deprecated Use sendMessage instead
     */
    public function testConnection(Request $request)
    {
        return $this->sendMessage($request);
    }
}
