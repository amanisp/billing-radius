<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsappBroadcastJob;
use App\Models\Connection;
use App\Models\Groups;
use App\Models\Invoice;
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
    private function humanDelay(int $index): int
    {
        // 1. Ubah ukuran batch menjadi 3 pesan agar lebih aman (Low-Profile)
        $batchSize = 3;
        $batchIndex = (int) floor($index / $batchSize);
        $positionInBatch = $index % $batchSize;

        // 2. Buat jeda antar pesan di dalam batch menjadi sangat ACAK
        // Daripada pakai kelipatan pasti (15, 30, 45), kita set rentang waktu acak.
        $delayInBatch = 0;
        if ($positionInBatch === 1) {
            // Pesan kedua di batch ini: jeda antara 15 sampai 22 detik dari awal batch
            $delayInBatch = rand(15, 22);
        } elseif ($positionInBatch === 2) {
            // Pesan ketiga di batch ini: jeda antara 35 sampai 45 detik dari awal batch
            $delayInBatch = rand(35, 45);
        }

        // 3. Jeda antar batch adalah 60 detik (1 Menit)
        // Batch 0 mulai di 0s, Batch 1 mulai di 60s, Batch 2 di 120s, dst.
        $batchDelay = $batchIndex * 60;

        // 4. Perlebar nilai Jitter agar benar-benar menghancurkan pola matematika
        $jitter = rand(2, 8);

        return $batchDelay + $delayInBatch + $jitter;
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

        // 1. Siapkan query dasar
        $query = Connection::where('group_id', $user->group_id)
            ->whereHas('member', fn($q) => $q->whereNotNull('phone_number'))
            ->when($validated['area'] !== 'all', fn($q) => $q->where('area_id', $validated['area']));

        // 2. Gunakan exists() alih-alih get() agar RAM tidak jebol
        if (!$query->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No members found for selected area'
            ], 404);
        }

        // 3. ✅ DEKLARASIKAN COUNTER SEBELUM CHUNK
        $counter = 0;

        // 4. Eksekusi chunk. (Panggil with('member') di sini agar query relasinya digabung)
        $query->with('member')->chunk(20, function ($chunk) use ($user, $validated, &$counter) {
            foreach ($chunk as $connection) {
                $member = $connection->member;
                if (!$member || !$member->phone_number) continue;

                $target = $this->formatWhatsappNumber($member->phone_number);
                if (!$target) continue;

                $delay = $this->humanDelay($counter);

                $message = "Yth. Pelanggan {$member->fullname}\n"
                    . "{$validated['subject']}\n\n"
                    . "{$validated['message']}";

                SendWhatsAppBroadcastJob::dispatch(
                    $user->group_id,
                    $target,
                    ['message' => $message],
                    null
                )->delay(now()->addSeconds($delay));

                // Increment counter untuk delay pesan selanjutnya
                $counter++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Broadcast queued successfully. Total: {$counter} messages."
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

        // 1. Dapatkan ID member yang memiliki setidaknya 1 invoice di bulan yang dipilih
        $unpaidMemberIds = Invoice::where('group_id', $user->group_id)
            ->where('status', 'unpaid')
            ->whereDate('created_at', '>=', $startOfMonth)
            ->whereDate('created_at', '<=', $endOfMonth)
            ->distinct()
            ->pluck('member_id');

        if ($unpaidMemberIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada invoice unpaid pada periode ini'
            ], 404);
        }

        $dueDateString = $endOfMonth->format('d/m/Y');
        $counter = 0;

        /**
         * 2. Lakukan chunk pada model MEMBER, bukan Invoice.
         * Tarik relasi invoices khusus yang statusnya unpaid.
         */
        Member::with([
            'connection.profile',
            'invoices' => function ($query) use ($user) {
                $query->where('group_id', $user->group_id)
                    ->where('status', 'unpaid')
                    ->orderBy('created_at', 'asc');
            }
        ])

            ->whereIn('id', $unpaidMemberIds)
            ->chunk(50, function ($members) use ($user, $dueDateString, &$counter) {
                foreach ($members as $member) {

                    $target = $this->formatWhatsappNumber($member->phone_number);

                    if (!$target) continue;
                    // Lewati jika karena alasan tertentu tidak ada invoice
                    if ($member->invoices->isEmpty()) continue;

                    // 3. Siapkan variabel untuk akumulasi tagihan
                    $totalAmount   = 0;
                    $totalDiscount = 0;
                    $periodList    = "";

                    // 4. Looping invoice milik member ini untuk membentuk list periode
                    foreach ($member->invoices as $index => $invoice) {
                        $totalAmount   += $invoice->amount ?? 0;
                        $totalDiscount += $invoice->discount ?? 0;

                        // Format: "1. Mei 2026\n2. Juni 2026"
                        $periodName = Carbon::parse($invoice->start_date)->locale('id')->translatedFormat('F Y');
                        $periodList .= ($index + 1) . ". " . $periodName . "\n";
                    }

                    $grandTotal = $totalAmount - $totalDiscount;

                    // Hapus sisa enter (\n) di akhir teks agar rapi
                    $periodList = trim($periodList);

                    $delay = $this->humanDelay($counter);

                    $variables = [
                        'full_name'     => $member->fullname ?? '-',
                        'uid'           => $member->connection->internet_number ?? '-',
                        'amount'        => number_format($invoice->amount, 0, ',', '.'),
                        'discount'      => number_format($totalDiscount, 0, ',', '.'),
                        'total'         => number_format($grandTotal, 0, ',', '.'),
                        'pppoe_user'    => $member->connection->username ?? '-',
                        'pppoe_profile' => $member->connection->profile->name ?? '-',
                        'period'        => $periodList, // <- List periode masuk ke sini
                        'ppn'           => 'Sudah Termasuk PPN 11%',
                        'due_date'      => $dueDateString,
                        'payment_url'   => 'https://bayar.amanisp.net.id',
                    ];

                    Log::info('DISPATCH INVOICE BROADCAST', [
                        'member_id'      => $member->id,
                        'total_invoices' => $member->invoices->count(), // Cek berapa invoice yang digabung
                        'target'         => $target,
                        'delay_sec'      => $delay
                    ]);

                    SendWhatsAppBroadcastJob::dispatch(
                        $user->group_id,
                        $target,
                        [
                            'template'  => 'invoice_terbit',
                            'group_id'  => $user->group_id,
                            'variables' => $variables
                        ],
                        null
                    )->delay(now()->addSeconds($delay));

                    $counter++;
                }
            });

        return response()->json([
            'success' => true,
            'message' => 'Invoice unpaid broadcast queued successfully. Total: ' . $counter . ' messages.'
        ]);
    }



    // public function broadcastInvoice(Request $request)
    // {
    //     $user = $this->getAuthUser();

    //     if (!$user) {
    //         return response()->json(['message' => 'Unauthorized'], 401);
    //     }

    //     $month = $request->get('month', now()->month);
    //     $year  = $request->get('year', now()->year);

    //     $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
    //     $endOfMonth   = Carbon::create($year, $month, 1)->endOfMonth();

    //     // Cek apakah ada invoice (opsional, menggunakan exists() lebih hemat memori)
    //     $hasInvoices = Invoice::where('group_id', $user->group_id)
    //         ->where('status', 'unpaid')
    //         ->whereDate('created_at', '>=', $startOfMonth)
    //         ->whereDate('created_at', '<=', $endOfMonth)
    //         ->exists();

    //     if (!$hasInvoices) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Tidak ada invoice unpaid pada periode ini'
    //         ], 404);
    //     }

    //     /**
    //      * 📦 Deklarasi variabel statis di luar loop untuk menghemat proses CPU
    //      */
    //     $periodString  = $startOfMonth->translatedFormat('F Y');
    //     $dueDateString = $endOfMonth->format('d/m/Y');

    //     // Taruh counter di LUAR closure agar tidak ke-reset
    //     $counter = 0;

    //     /**
    //      * 🔄 Menggunakan Query Builder Chunking (Lebih hemat RAM)
    //      */
    //     Invoice::with([
    //         'member.paymentDetail',
    //         'connection.profile'
    //     ])
    //         ->where('group_id', $user->group_id)
    //         ->where('status', 'unpaid')
    //         ->whereDate('created_at', '>=', $startOfMonth)
    //         ->whereDate('created_at', '<=', $endOfMonth)
    //         ->chunk(50, function ($invoices) use ($user, $periodString, $dueDateString, &$counter) {
    //             // Perhatikan penggunaan '&$counter' (passed by reference) 
    //             // agar variabel di luar closure ikut bertambah nilainya.

    //             foreach ($invoices as $invoice) {
    //                 $member = $invoice->member;
    //                 $target = $this->formatWhatsappNumber($member->phone_number);

    //                 if (!$target) continue;

    //                 // Delay aman untuk Unofficial API: (Counter * 5 detik) + random 1-3 detik
    //                 // Contoh: Pesan 1 (1s), Pesan 2 (6s), Pesan 3 (12s), dst.
    //                 $delay = $this->humanDelay($counter);


    //                 $amount   = $invoice->amount ?? 0;
    //                 $discount = $invoice->discount ?? 0;
    //                 $total    = $amount - $discount;

    //                 $variables = [
    //                     'full_name'     => $member->fullname ?? '-',
    //                     'uid'           => $member->connection->internet_number ?? '-',
    //                     'amount'        => number_format($amount, 0, ',', '.'),
    //                     'discount'      => number_format($discount, 0, ',', '.'),
    //                     'total'         => number_format($total, 0, ',', '.'),
    //                     'pppoe_user'    => $member->connection->username ?? '-',
    //                     'pppoe_profile' => $member->connection->profile->name ?? '-',
    //                     'period'        => $periodString,
    //                     'ppn'           => 'Sudah Termasuk PPN 11%',
    //                     'due_date'      => $dueDateString,
    //                     'payment_url'   => 'https://bayar.amanisp.net.id',
    //                 ];

    //                 Log::info('DISPATCH INVOICE BROADCAST', [
    //                     'invoice_id' => $invoice->id,
    //                     'target'     => $target,
    //                     'delay_sec'  => $delay
    //                 ]);

    //                 SendWhatsAppBroadcastJob::dispatch(
    //                     $user->group_id,
    //                     $target,
    //                     [
    //                         'template'  => 'invoice_terbit',
    //                         'group_id'  => $user->group_id,
    //                         'variables' => $variables
    //                     ],
    //                     null
    //                 )->delay(now()->addSeconds($delay));

    //                 // Increment counter untuk queue selanjutnya
    //                 $counter++;
    //             }
    //         });

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Invoice unpaid broadcast queued successfully. Total: ' . $counter . ' messages.'
    //     ]);
    // }
}
