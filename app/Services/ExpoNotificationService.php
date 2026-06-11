<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoNotificationService
{
    /**
     * Mengirim notifikasi pembayaran sukses ke Admin dan Mitra dalam satu grup.
     *
     * @param int $groupId ID Grup dari user yang memproses transaksi
     * @param object $invoice Data objek Invoice
     * @param object $member Data objek Member (Pelanggan)
     * @param string $paymentMethod Metode pembayaran ('cash' atau 'bank_transfer')
     * @return void
     */
    public function sendPaymentSuccessNotification($groupId, $invoice, $member, $paymentMethod)
    {
        Log::info('Mulai proses ExpoNotificationService', [
            'group_id' => $groupId,
            'invoice_id' => $invoice->id
        ]);

        try {
            // 1. Cari data token milik admin & mitra di group yang sama
            $tokens = User::where('group_id', $groupId)
                ->whereIn('role', ['admin', 'mitra'])
                ->whereNotNull('expo_push_token')
                ->pluck('expo_push_token')
                ->toArray();

            // Log jumlah token yang ditemukan
            Log::info('Token Expo ditemukan', [
                'jumlah_token' => count($tokens),
                'tokens' => $tokens // Tampilkan token untuk memastikan datanya benar
            ]);

            if (empty($tokens)) {
                Log::warning('Expo Push Notification dibatalkan: Tidak ada token yang valid untuk group_id ini.');
                return;
            }

            // 2. Siapkan format pesan
            $methodName = $paymentMethod === 'cash' ? 'Cash' : 'Transfer';
            $messages = [];

            foreach ($tokens as $token) {
                $messages[] = [
                    'to'    => $token,
                    'sound' => 'default',
                    'title' => 'Pembayaran Diterima 💸',
                    'body'  => "Invoice {$invoice->inv_number} atas nama {$member->fullname} telah dibayar via {$methodName}.",
                    'data'  => [
                        'invoice_id' => $invoice->id,
                        'action'     => 'view_invoice'
                    ]
                ];
            }

            // 3. Kirim via API Expo
            // Expo membatasi max 100 pesan per request
            $chunks = array_chunk($messages, 100);
            foreach ($chunks as $index => $chunk) {

                Log::info("Mengirim Chunk #{$index} ke API Expo...");

                $response = Http::withHeaders([
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ])->post('https://exp.host/--/api/v2/push/send', $chunk);

                // $response = Http::withoutVerifying()
                //     ->withHeaders([
                //         'Accept'       => 'application/json',
                //         'Content-Type' => 'application/json',
                //     ])->post('https://exp.host/--/api/v2/push/send', $chunk);

                // 4. Analisa Response dari Expo
                if ($response->successful()) {
                    Log::info("Chunk #{$index} berhasil dikirim ke Expo.", [
                        'response' => $response->json()
                    ]);
                } else {
                    Log::error("Gagal mengirim Chunk #{$index} ke Expo", [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Tangkap segala error yang terjadi selama proses
            Log::error('Terjadi Exception di ExpoNotificationService', [
                'invoice_id' => $invoice->id ?? null,
                'pesan_error' => $e->getMessage(),
                'baris' => $e->getLine(),
                'file' => $e->getFile()
            ]);
        }
    }
}
