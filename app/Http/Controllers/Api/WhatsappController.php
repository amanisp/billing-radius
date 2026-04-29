<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsappBroadcastJob;
use App\Models\Connection;
use App\Models\Groups;
use App\Models\Member;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use App\Services\FonnteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\WhatsappCoreService;

class WhatsappController extends Controller
{
    protected $fonnte;

    public function __construct(FonnteService $fonnte)
    {
        $this->fonnte = $fonnte;
    }


    private function formatWhatsappNumber(?string $phone): ?string
    {
        if (!$phone) return null;

        // ambil hanya angka
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (!$phone) return null;

        // ubah ke format 62
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone . '@s.whatsapp.net';
    }


    public function whatsappLog(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $query = WhatsappMessageLog::where('group_id', $user->group_id);

            // 🔍 Search by recipient atau message
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('recipient', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            }

            // 🔽 Filter by status
            if ($status = $request->get('status')) {
                $query->where('status', $status); // queued, sent, failed
            }

            // 🔽 Filter by type
            if ($type = $request->get('type')) {
                $query->where('type', $type);
            }

            // 📅 Filter by tanggal
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // 🔄 Sort
            $allowedSorts = ['id', 'recipient', 'status', 'type', 'sent_at', 'created_at'];
            $sortField     = in_array($request->get('sort_field'), $allowedSorts)
                ? $request->get('sort_field')
                : 'created_at'; // ✅ default created_at lebih masuk akal dari 'id'
            $sortDirection = $request->get('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = min((int) $request->get('per_page', 15), 100); // ✅ max 100 per page
            $logs    = $query->paginate($perPage);

            // ✅ Format response konsisten dengan controller lain
            return ResponseFormatter::success([
                'items' => $logs->items(),
                'meta'  => [
                    'current_page' => $logs->currentPage(),
                    'per_page'     => $logs->perPage(),
                    'total'        => $logs->total(),
                    'last_page'    => $logs->lastPage(),
                ],
                // ✅ summary statistik
                'summary' => [
                    'total'  => WhatsappMessageLog::where('group_id', $user->group_id)->count(),
                    'sent'   => WhatsappMessageLog::where('group_id', $user->group_id)->where('status', 'sent')->count(),
                    'failed' => WhatsappMessageLog::where('group_id', $user->group_id)->where('status', 'failed')->count(),
                    'queued' => WhatsappMessageLog::where('group_id', $user->group_id)->where('status', 'queued')->count(),
                ],
            ], 'Data log WhatsApp berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }


    public function loginQr(WhatsappCoreService $service)
    {

        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }


            $group = Groups::findOrFail($user->group_id); // atau sesuai logic kamu

            // 🔥 pastikan device ada
            $deviceId = $service->ensureDevice($group);

            // 🔥 baru login
            $response = $service->login($deviceId);

            $data = [
                'device_id' => $deviceId,
                'qr_link' => $response->json('results.qr_link')
            ];

            return ResponseFormatter::success($data, 'QR Code berhasil digenerate!');
        } catch (\Throwable $th) {
            return ResponseFormatter::error($th, 'QR Code gagal digenerate!', 200);
        }
    }

    public function status(WhatsappCoreService $service)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // 🔹 ambil / generate device id dari group
            $group = Groups::findOrFail($user->group_id);

            $deviceId = $group->wa_api_token; // ambil aja tanpa ensure

            if (!$deviceId) {
                return ResponseFormatter::success([
                    'state' => 'not_registered'
                ], 'Device belum dibuat');
            }
            // 🔹 ambil status + avatar otomatis
            $result = $service->getDeviceStatusWithAvatar($deviceId);

            $data = [
                'phone'        => $result['phone'] ?? null,
                'display_name' => $result['status']['results']['display_name'] ?? null,
                'state'        => $result['status']['results']['state'] ?? null,
                'avatar_url'   => $result['avatar']['results']['url'] ?? null,
            ];

            return ResponseFormatter::success($data, 'QR Code berhasil digenerate!');
        } catch (\Throwable $th) {
            return ResponseFormatter::error($th, 'Status whatsapp tidak ada!', 200);
        }
    }

    public function disconnect(Request $request, WhatsappCoreService $service)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // 1. Ambil deviceId secara otomatis berdasarkan group_id user
            // Mengikuti pola yang sama dengan method status Anda
            $group = Groups::findOrFail($user->group_id);

            $deviceId = $group->wa_api_token; // ambil aja tanpa ensure

            if (!$deviceId) {
                return ResponseFormatter::success([
                    'state' => 'not_registered'
                ], 'Device belum dibuat');
            }

            // 2. Panggil fungsi disconnect dari service
            $result = $service->disconnectDevice($deviceId);

            if ($result['success']) {
                return ResponseFormatter::success(
                    $result['data'] ?? null,
                    'WhatsApp Device disconnected successfully!'
                );
            }

            return ResponseFormatter::error(
                null,
                $result['message'] ?? 'Gagal memutuskan koneksi WhatsApp',
                $result['status'] ?? 500
            );
        } catch (\Throwable $th) {
            return ResponseFormatter::error($th, 'Terjadi kesalahan sistem saat diskonek!', 500);
        }
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

        // Chunk setiap 5 koneksi
        $connections->chunk(5)->each(function ($chunk, $batchIndex) use ($user, $validated) {

            $counter = 0;

            foreach ($chunk as $connection) {

                $member = $connection->member;

                if (!$member || !$member->phone_number) continue;

                $target = $this->formatWhatsappNumber($member->phone_number);
                if (!$target) continue;

                $delay = ($counter * 2) + rand(1, 3);

                // ✅ message per user
                $message = "Yth. Pelanggan {$member->fullname}\n"
                    . "{$validated['subject']}\n\n"
                    . "{$validated['message']}";

                Log::info('DISPATCH AREA JOB', [
                    'target' => $target,
                    'delay'  => $delay
                ]);

                SendWhatsAppBroadcastJob::dispatch(
                    $user->group_id,
                    $target,
                    ['message' => $message],
                    []
                )->delay(now()->addSeconds($delay));

                $counter++; // ✅ penting!
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
        $members->chunk(10)->each(function ($chunk, $batchIndex) use ($user, $year, $month) {
            $counter = 0;
            foreach ($chunk as $index => $member) {
                $target = $this->formatWhatsappNumber($member->phone_number);

                if (!$target) continue;
                $delay = ($counter * 2) + rand(1, 3);

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

                Log::info('DISPATCH JOB', [
                    'target' => $target,
                    'delay'  => $delay
                ]);

                // Dispatch job dengan delay batch
                SendWhatsAppBroadcastJob::dispatch(
                    $user->group_id,
                    $target,
                    [
                        'template'  => 'invoice_terbit',
                        'variables' => $variables
                    ],
                    [] // options: bisa isi delay per pesan
                )->delay(now()->addSeconds($delay));
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Invoice broadcast queued successfully, messages will be sent shortly.'
        ]);
    }
}
