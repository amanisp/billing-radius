<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppApiController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Handle incoming webhook from WhatsApp
     */
    public function webhook(Request $request)
    {
        // Get the JSON body
        $data = $request->all();
        $user = $request->user();

        // Log incoming webhook (you’ll find it in storage/logs/laravel.log)
        Log::info('WhatsApp Webhook received', [
            'groupId' => $request->groupId,
            'payload' => $data
        ]);

        // Extract important fields
        $message = $data['message'] ?? null;
        $from = $data['from'] ?? null;
        $isGroup = $data['isGroup'] ?? null;
        $isMe = $data['isMe'] ?? null;


        return response()->json([
            'status' => 'success',
        ]);
    }

    /**
     * Send WhatsApp message
     */
    public function sendMessage(Request $request)
    {
        try {
            $request->validate([
                'api_key' => 'required|string',
                'receiver' => 'required|string',
                'message' => 'required|string',
            ]);

            // $result = $this->whatsappService->sendTextMessage(
            //     $request->receiver,
            //     $request->message
            // );
            $result = $this->whatsappService->sendTextMessage(
                $request->api_key,
                $request->receiver,
                $request->message
            );
            $result = null;
            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'data' => $result['data']
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send WhatsApp message',
                'error' => $result['data'] ?? $result['error']
            ], $result['status']);

        } catch (\Exception $e) {
            Log::error('WhatsApp Send Message Error:', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while sending the message'
            ], 500);
        }
    }
}
