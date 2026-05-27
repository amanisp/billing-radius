<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\User;
use App\Http\Controllers\ActivityLogController;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendWhatsAppBroadcastJob;

class BulkManualPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $invoiceIds;
    protected $paymentMethod;
    protected $userId;
    protected $groupId;

    // Timeout job jika datanya banyak
    public $timeout = 120;

    public function __construct($invoiceIds, $paymentMethod, $userId, $groupId)
    {
        $this->invoiceIds = $invoiceIds;
        $this->paymentMethod = $paymentMethod;
        $this->userId = $userId;
        $this->groupId = $groupId;
    }

    public function handle()
    {
        $user = User::find($this->userId);
        if (!$user) return;

        $paymentMethodLabel = $this->paymentMethod === 'bank_transfer' ? 'Transfer Bank' : 'Cash';

        // 1. Ambil semua invoice yang valid dan masih unpaid
        $invoices = Invoice::with(['member.connection.profile'])
            ->whereIn('id', $this->invoiceIds)
            ->where('group_id', $this->groupId)
            ->where('status', 'unpaid')
            ->get();

        if ($invoices->isEmpty()) return;

        $counter = 0;

        DB::beginTransaction();

        try {
            foreach ($invoices as $invoice) {
                // 2. Update status invoice
                $invoice->update([
                    'status'         => 'paid',
                    'payment_method' => $this->paymentMethod,
                    'paid_at'        => now(),
                    'payer_id'       => $this->userId,
                ]);

                // 3. Catat log
                ActivityLogController::logCreate([
                    'invoice_id' => $invoice->id,
                    'member_id'  => $invoice->member_id,
                    'amount'     => $invoice->amount,
                    'method'     => $this->paymentMethod,
                    'action'     => 'manual_payment_bulk',
                    'status'     => 'success',
                ], 'invoices');

                // 4. Siapkan notifikasi WhatsApp
                $member = $invoice->member;
                if ($member) {
                    $target = $this->formatWhatsappNumber($member->phone_number);

                    if ($target) {
                        $delay = $this->humanDelay($counter);

                        $period = Carbon::parse($invoice->start_date)
                            ->locale('id')
                            ->translatedFormat('F Y');

                        $variables = [
                            'full_name'       => $member->fullname ?? '-',
                            'no_invoice'      => $invoice->inv_number ?? '-',
                            'total'           => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                            'pppoe_user'      => $member->connection->username ?? '-',
                            'pppoe_profile'   => $member->connection->profile->name ?? '-',
                            'period'          => $period,
                            'payment_gateway' => $paymentMethodLabel,
                            'footer'          => 'PT. Anugerah Media Data Nusantara',
                        ];

                        Log::info('DISPATCH INVOICE PAID BROADCAST VIA JOB', [
                            'invoice_id' => $invoice->id,
                            'target'     => $target,
                            'delay_sec'  => $delay
                        ]);

                        // Dispatch WA Job
                        SendWhatsAppBroadcastJob::dispatch(
                            $member->group_id,
                            $target,
                            [
                                'template'  => 'payment_paid',
                                'group_id'  => $member->group_id,
                                'variables' => $variables
                            ],
                            null
                        )->delay(now()->addSeconds($delay));

                        $counter++;
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Bulk Manual Payment Job Failed', [
                'error' => $th->getMessage()
            ]);
        }
    }

    /**
     * Hitung jeda (delay) pengiriman agar tidak terkena limit WhatsApp
     */
    private function humanDelay(int $index): int
    {
        // 1 Batch = 5 pesan
        $batchIndex = (int) floor($index / 5); // Menentukan ini masuk kelompok/batch ke berapa (0, 1, 2, dst)
        $positionInBatch = $index % 5;         // Posisi pesan di dalam batch tersebut (0, 1, 2, 3, 4)

        // Minimal jeda pesan 15 detik
        $delayInBatch = $positionInBatch * 15;

        // Total durasi 1 batch (5 pesan * 15 detik = 60 detik) 
        // + jeda antar batch 45 detik = 105 detik per siklus batch.
        $batchDelay = $batchIndex * 105;

        // Tambahkan sedikit angka acak (jitter) 1-3 detik agar tidak terlalu robotik
        $jitter = rand(1, 3);

        // Total detik dari waktu "SEKARANG" job ini harus dieksekusi
        return $batchDelay + $delayInBatch + $jitter;
    }

    /**
     * Format nomor HP agar sesuai dengan format yang diterima oleh API WhatsApp
     */
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
}
