<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Member;
use App\Models\GlobalSettings;
use App\Jobs\BulkInvoiceJob;
use Carbon\Carbon;
use App\Http\Controllers\ActivityLogController; // Pastikan namespace ini sesuai

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'invoice:generate-bulk';
    protected $description = 'Otomatis generate invoice member berdasarkan tanggal setting di GlobalSettings';

    public function handle()
    {
        $today = Carbon::now();
        $currentDay = $today->day; // Mengambil tanggal hari ini (1-31)
        $startMonthYear = $today->format('Y-m'); // Format Y-m untuk bulan berjalan

        $this->info("Memulai pengecekan invoice otomatis untuk tanggal: " . $today->toDateString());

        // 1. Cari group_id di GlobalSettings yang jadwal generate-nya hari ini
        $settings = GlobalSettings::where('invoice_generate_days', $currentDay)->get();

        if ($settings->isEmpty()) {
            $this->info("Tidak ada grup yang di-setting untuk generate invoice pada tanggal $currentDay.");
            return;
        }

        // Kumpulkan semua group_id yang ditemukan
        $groupIds = $settings->pluck('group_id')->toArray();
        $this->info("Ditemukan " . count($groupIds) . " grup yang akan diproses (Group ID: " . implode(', ', $groupIds) . ").");

        // 2. Ambil semua member yang berada di group_id tersebut (menggunakan whereIn)
        $members = Member::with([
            'connection.area',
            'paymentDetail'
        ])
            ->whereIn('group_id', $groupIds)
            ->get();

        if ($members->isEmpty()) {
            $this->info("Tidak ada member yang ditemukan untuk grup tersebut.");
            return;
        }

        $dispatchedCount = 0;
        $delayInSeconds = 0;

        // 3. Looping dan masukkan ke Queue
        foreach ($members as $member) {
            if (!$member->paymentDetail) {
                continue;
            }

            $amount = (float) $member->paymentDetail->amount;

            $payload = [
                'member_id'           => $member->id,
                'amount'              => $amount,
                'start_month_year'    => $startMonthYear,
                'subscription_period' => 1,
            ];

            BulkInvoiceJob::dispatch($payload)->delay(now()->addSeconds($delayInSeconds));

            $delayInSeconds += 2;
            $dispatchedCount++;
        }

        if ($dispatchedCount === 0) {
            $this->warn("Member ditemukan, tapi tidak ada yang memiliki detail pembayaran (paymentDetail) valid.");
            return;
        }

        // 4. Catat ke Activity Log
        try {
            // Catatan: Karena ini berjalan di background (CRON), Auth::user() akan bernilai null.
            // Pastikan ActivityLogController Anda tidak error jika Auth::user() kosong (bisa diset 'user_id' = System/Null)
            ActivityLogController::logCreate([
                'action' => 'auto_bulk_create_invoice',
                'status' => 'queued',
                'total'  => $dispatchedCount,
                'groups' => implode(', ', $groupIds)
            ], 'invoices');
        } catch (\Exception $e) {
            $this->error("Gagal mencatat log aktivitas: " . $e->getMessage());
        }

        $this->info("Berhasil menambahkan $dispatchedCount invoice ke dalam antrean (queue) untuk bulan $startMonthYear.");
    }
}
