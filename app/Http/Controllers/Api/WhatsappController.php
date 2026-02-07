<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppBroadcastJob;
use App\Models\Area;
use App\Models\Connection;
use App\Models\GlobalSettings;
use App\Models\Groups;
use App\Models\Member;
use App\Models\User;
use App\Services\FonnteService;
use App\Models\WhatsappMessageLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    protected $fonnte;

    public function __construct(FonnteService $fonnte)
    {
        $this->fonnte = $fonnte;
    }

    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }

    public function status()
    {
        $groupId = Auth::user()->group_id;
        $result = $this->fonnte->getDeviceStatus($groupId);

        return $result['success']
            ? response()->json(['success' => true, 'data' => $result['data']])
            : response()->json(['success' => false, 'message' => $result['error']], $result['status'] ?? 500);
    }

    public function broadcastArea(Request $request)
    {
        $user = $this->getAuthUser();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'area'    => 'required', // bisa 'all' atau ID
            'message' => 'required|string'
        ]);

        // Ambil koneksi sesuai area
        $query = Connection::with('member')
            ->where('group_id', $user->group_id)
            ->whereHas('member', fn($q) => $q->whereNotNull('phone_number'));

        if ($validated['area'] !== 'all') {
            $query->where('area_id', $validated['area']);
        }

        $connections = $query->get();

        if ($connections->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No members found for selected area'
            ], 404);
        }

        $message = "Yth. Pelanggan\n{$validated['subject']}\n\n{$validated['message']}";

        // Chunk setiap 5 koneksi
        $connections->chunk(5)->each(function ($chunk, $batchIndex) use ($user, $message) {
            foreach ($chunk as $conn) {
                $target = $conn->member->phone_number;

                // Dispatch job dengan delay batch
                SendWhatsAppBroadcastJob::dispatch(
                    $user->group_id,            // groupId
                    $target,                    // nomor tujuan
                    ['message' => $message],    // data pesan custom
                    []                          // options, bisa isi 'delay'=>10 jika mau delay per pesan
                )->delay(now()->addSeconds($batchIndex * 10)); // delay per batch 10 detik
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Broadcast queued successfully, messages will be sent shortly.'
        ]);
    }

    public function broadcastInvoice(Request $request)
    {
        $user = $this->getAuthUser();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth   = Carbon::create($year, $month, 1)->endOfMonth();

        // Ambil member yang belum bayar bulan ini
        $membersQuery = Member::with(['paymentDetail', 'connection.profile'])
            ->where('group_id', $user->group_id)
            ->whereDoesntHave('invoices', function ($q) use ($startOfMonth, $endOfMonth) {
                $q->where('status', 'paid')
                    ->whereDate('start_date', '<=', $endOfMonth)
                    ->whereDate('due_date', '>=', $startOfMonth);
            });


        $members = $membersQuery->get();

        if ($members->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No members found for selected area/month'
            ], 404);
        }


        // Chunk setiap 5 member
        $members->chunk(5)->each(function ($chunk, $batchIndex) use ($user, $year, $month) {
            foreach ($chunk as $member) {
                $target = $member->phone_number ?? null;
                if (!$target) continue;


                $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
                $endOfMonth   = Carbon::create($year, $month, 1)->endOfMonth();
                $amount = $member->paymentDetail->amount ?? 0;
                $discount = $member->paymentDetail->discount ?? 0;

                $total = $amount - $discount;
                // Variabel untuk template
                $variables = [
                    'full_name'     => $member->fullname,
                    'uid'           => $member->connection->internet_number ?? '-',          // ID Pelanggan
                    'amount'        => number_format($amount ?? 0, 0, ',', '.'),
                    'discount'      => number_format($discount ?? 0, 0, ',', '.'),
                    'total'         => number_format($total ?? 0, 0, ',', '.'),
                    'pppoe_user'    => $member->connection->username ?? '-',
                    'pppoe_profile' => $member->connection->profile->name ?? '-',
                    'period'        => $startOfMonth->translatedFormat('F Y'),  // contoh: "Februari 2026"
                    'due_date'      => $endOfMonth->format('d/m/Y'),           // contoh: "28/02/2026"
                    'payment_url'   => 'https://bayar.amanisp.net.id',
                    'footer'        => "Pembayaran diatas sudah termasuk PPn 11%\nPembayaran Manual silahkan hubungi admin kami."
                ];


                // Dispatch job dengan delay batch
                SendWhatsAppBroadcastJob::dispatch(
                    $user->group_id,
                    $target,
                    [
                        'template'  => 'invoice_terbit',
                        'variables' => $variables
                    ],
                    [] // options: bisa isi delay per pesan
                )->delay(now()->addSeconds($batchIndex * 10));
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Invoice broadcast queued successfully, messages will be sent shortly.'
        ]);
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
