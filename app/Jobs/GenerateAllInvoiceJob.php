<?php

namespace App\Jobs;

use App\Models\InvoiceHomepass;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateAllInvoiceJob implements ShouldQueue
{
    use Queueable;

    protected $member;

    public function __construct(Member $member)
    {
        $this->member = $member;
    }

    public function handle()
    {
        $member = $this->member;
        $pd = $member->paymentDetail;

        //  Validation checks
        if (!$pd) {
            Log::warning("⚠️ Member ID {$member->id} tidak memiliki PaymentDetail, dilewati.");
            return;
        }

        if (!$member->connection) {
            Log::warning("⚠️ Member ID {$member->id} tidak memiliki koneksi aktif, dilewati.");
            return;
        }

        //  Pakai method getBaseDate() dari model
        $baseDate = $pd->getBaseDate();
        $endOfCurrentMonth = Carbon::now()->endOfMonth();
        $lastCreatedDate = null;

        // Loop per bulan mulai dari baseDate sampai akhir bulan ini
        while ($baseDate->lessThanOrEqualTo($endOfCurrentMonth)) {
            $dueDate = $baseDate->copy()->endOfMonth();

            //  Pakai method hasInvoiceForMonth() dari model
            if ($pd->hasInvoiceForMonth($dueDate)) {
                Log::info("ℹ️ Invoice untuk member {$member->id} bulan {$dueDate->format('Y-m')} sudah ada, dilewati.");
            } else {
                try {
                    //  Pakai method generateInvoiceForMonth() dari model
                    $invoice = $pd->generateInvoiceForMonth($dueDate, 1);

                    $lastCreatedDate = $dueDate->toDateString();

                    Log::info("✅ Invoice dibuat untuk member {$member->id} periode {$dueDate->format('F Y')} - {$invoice->inv_number}");
                } catch (\Throwable $th) {
                    Log::error("❌ Gagal generate invoice untuk member {$member->id} bulan {$dueDate->format('F Y')}: {$th->getMessage()}");
                }
            }

            // Naik ke bulan berikutnya
            $baseDate->addMonthNoOverflow();
        }

        //  Update last_invoice pakai method updateLastInvoice()
        if ($lastCreatedDate) {
            $pd->updateLastInvoice($lastCreatedDate);
            Log::info("📝 Update last_invoice member {$member->id} → {$lastCreatedDate}");
        }
    }
}
    // public function generateInvoiceForMonth(Carbon $dueDate, $attempt = 1)
    // {
    //     $member = $this->member;

    //     if (!$member || !$member->billing || !$member->connection) {
    //         throw new \Exception("Member tidak memiliki data billing atau koneksi.");
    //     }

    //     // Cek apakah invoice untuk bulan tersebut sudah ada
    //     if ($this->hasInvoiceForMonth($dueDate)) {
    //         throw new \Exception("Invoice untuk bulan {$dueDate->format('F Y')} sudah ada.");
    //     }

    //     // Generate nomor invoice
    //     $invNumber = 'INV-' . strtoupper(uniqid());

    //     // Hitung total amount dari billing
    //     $totalAmount = $member->billing->monthly_fee; // Contoh sederhana

    //     // Panggil Xendit API untuk membuat invoice
    //     $xenditInvoice = XenditService::createInvoice([
    //         'external_id' => $invNumber,
    //         'amount' => $totalAmount,
    //         'payer_email' => $member->email,
    //         'description' => "Invoice untuk {$dueDate->format('F Y')}",
    //         'due_date' => $dueDate->toIso8601String(),
    //     ]);

    //     // Simpan invoice ke database
    //     $invoice = InvoiceHomepass::create([
    //         'member_id' => $member->id,
    //         'connection_id' => $member->connection->id,
    //         'invoice_type' => 'H',
    //         'start_date' => $dueDate->copy()->startOfMonth()->toDateString(),
    //         'due_date' => $dueDate->toDateString(),
    //         'subscription_period' => $dueDate->format('F Y'),
    //         'inv_number' => $invNumber,
    //         'amount' => $totalAmount,
    //         'status' => 'unpaid',
    //         'group_id' => $this->group_id,
    //         'payment_url' => $xenditInvoice['invoice_url'],
    //     ]);
    //     // Update last_invoice
    //     $this->updateLastInvoice($dueDate->toDateString());
    //     return $invoice;
    // }
