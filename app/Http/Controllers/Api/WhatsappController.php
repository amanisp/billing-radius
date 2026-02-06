<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GlobalSettings;
use App\Models\Groups;
use App\Services\FonnteService;
use App\Models\WhatsappMessageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsAppController extends Controller
{
    protected $fonnte;

    public function __construct(FonnteService $fonnte)
    {
        $this->fonnte = $fonnte;
    }

    public function status()
    {
        $groupId = Auth::user()->group_id;
        $result = $this->fonnte->getDeviceStatus($groupId);

        return $result['success']
            ? response()->json(['success' => true, 'data' => $result['data']])
            : response()->json(['success' => false, 'message' => $result['error']], $result['status'] ?? 500);
    }

    public function sendMessage(Request $request)
    {
        $groupId = Auth::user()->group_id;
        $validated = $request->validate([
            'target' => 'required|string',
            'message' => 'required|string',
            'device' => 'nullable|string',
            'footer' => 'nullable|string',
            'delay' => 'nullable|integer|min:0|max:60',
        ]);

        $result = $this->fonnte->sendText($groupId, $validated['target'], $validated['message'], $request->only(['device', 'footer', 'delay']));

        if ($result['success']) {
            WhatsappMessageLog::create([
                'group_id' => $groupId,
                'recipient' => $validated['target'],
                'message' => $validated['message'],
                'status' => 'sent',
                'type' => 'single',
                'sent_at' => now(),
                'response_data' => json_encode($result['data'])
            ]);
        }

        return $result['success']
            ? response()->json(['success' => true, 'message' => 'Sent!', 'data' => $result['data']])
            : response()->json(['success' => false, 'message' => $result['error']], $result['status'] ?? 500);
    }

    public function broadcast(Request $request)
    {
        $groupId = Auth::user()->group_id;
        $validated = $request->validate([
            'targets' => 'required|array|min:1|max:1000',
            'targets.*' => 'required|string',
            'message' => 'required|string|max:1000',
            'min_delay' => 'nullable|integer|min:1|max:30',  // 1-30s
            'max_delay' => 'nullable|integer|min:2|max:60',  // 2-60s
        ]);

        $minDelay = $request->min_delay ?? 2;
        $maxDelay = $request->max_delay ?? 10;

        $result = $this->fonnte->sendBroadcast(
            $groupId,
            $validated['targets'],
            $validated['message'],
            $minDelay,
            $maxDelay
        );


        // Log semua targets (tetap log meski gagal)
        foreach ($validated['targets'] as $target) {
            WhatsappMessageLog::create([
                'group_id' => $groupId,
                'recipient' => $target,
                'message' => $validated['message'],
                'status' => 'sent', // ubah jadi 'pending' jika mau track status
                'type' => 'broadcast',
                'sent_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Broadcast completed',
            'data' => $result['data'],
            'summary' => $result['summary']
        ]);
    }


    public function generateQR(Request $request)
    {
        $groupId = Auth::user()->group_id;
        $validated = $request->validate([
            'device' => 'nullable|string',
            'autoread' => 'nullable|boolean',
        ]);

        $result = $this->fonnte->getQR($groupId, $validated['device'] ?? null, $validated['autoread'] ?? true);

        return $result['success']
            ? response()->json(['success' => true, 'data' => $result['data']])
            : response()->json(['success' => false, 'message' => $result['error']], $result['status'] ?? 500);
    }

    public function disconnect(Request $request)
    {
        $groupId = Auth::user()->group_id;
        $validated = $request->validate(['device' => 'nullable|string']);

        $result = $this->fonnte->disconnect($groupId, $validated['device'] ?? null);

        return $result['success']
            ? response()->json(['success' => true, 'message' => 'Disconnected!', 'data' => $result['data']])
            : response()->json(['success' => false, 'message' => $result['error']], $result['status'] ?? 500);
    }

    public function templates()
    {
        return response()->json([
            'success' => true,
            'data' => $this->fonnte->getTemplates()
        ]);
    }

    public function updateWhatsappToken(Request $request)
    {
        $request->validate(['whatsapp_token' => 'required|string']);

        GlobalSettings::updateOrCreate(
            ['group_id' => Auth::user()->group_id],
            ['whatsapp_api_key' => $request->whatsapp_token]
        );

        return response()->json(['success' => true, 'message' => 'Account token saved!']);
    }

    public function debugTokens()
    {
        $groupId = Auth::user()->group_id;

        return response()->json([
            'success' => true,
            'debug' => [
                'account_token_configured' => !empty(config('services.fonnte.account_token')),
                'account_token_db' => !empty(GlobalSettings::getWhatsAppApiKey($groupId)),
                'device_token_db' => !empty(Groups::find($groupId)?->wa_api_token),
                'devices_status' => $this->fonnte->getDeviceStatus($groupId),
            ]
        ]);
    }
}
