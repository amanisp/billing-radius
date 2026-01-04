<?php

namespace App\Jobs;

use App\Helpers\InvoiceHelper;
use App\Models\InvoiceHomepass;
use App\Models\Member;
use App\Models\PaymentDetail;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;

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
        $apiInstance = new InvoiceApi();

        $pd = $member->paymentDetail;
        if (!$pd) {
            Log::warning("⚠️ Member ID {$member->id} tidak memiliki PaymentDetail, dilewati.");
            return;
        }

        if (!$member->connection) {
            Log::warning("⚠️ Member ID {$member->id} tidak memiliki koneksi aktif, dilewati.");
            return;
        }

        $price    = $pd->amount ?? 0;
        $vat      = $pd->ppn ?? 0;
        $discount = $pd->discount ?? 0;
        $periode  = 1;

        $total_amount = (($price + ($price * $vat / 100)) - $discount) * $periode;
        $duration = InvoiceHelper::invoiceDurationThisMonth();

        // === Tentukan baseDate ===
        if ($pd->last_invoice) {
            $baseDate = Carbon::parse($pd->last_invoice)->addMonthNoOverflow(); // lanjut bulan berikutnya
        } elseif ($pd->active_date) {
            $baseDate = Carbon::parse($pd->active_date);
        } else {
            $baseDate = Carbon::now();
        }

        // === Batas maksimal hanya sampai akhir bulan saat ini ===
        $endOfCurrentMonth = Carbon::now()->endOfMonth();
        $lastCreatedDate = null;

        // Loop per bulan mulai dari baseDate sampai akhir bulan ini
        while ($baseDate->lessThanOrEqualTo($endOfCurrentMonth)) {
            $dueDate = $baseDate->copy()->endOfMonth(); // due_date = akhir bulan

            // Cegah duplikasi invoice
            $exists = InvoiceHomepass::where('member_id', $member->id)
                ->whereYear('due_date', $dueDate->year)
                ->whereMonth('due_date', $dueDate->month)
                ->exists();

            if ($exists) {
                Log::info("ℹ️ Invoice untuk member {$member->id} bulan {$dueDate->format('Y-m')} sudah ada, dilewati.");
            } else {
                // Generate nomor invoice
                $invNumber = InvoiceHelper::generateInvoiceNumber(
                    $member->connection->area_id ?? 1,
                    'H'
                );

                // Buat invoice ke Xendit
                $create_invoice_request = new CreateInvoiceRequest([
                    'external_id'      => $invNumber,
                    'description'      => 'Tagihan nomor internet ' . ($member->connection->internet_number ?? '-') .
                        ' Periode: ' . $dueDate->format('F Y'),
                    'amount'           => intval($total_amount),
                    'invoice_duration' => $duration,
                    'currency'         => 'IDR',
                    'payer_email'      => $member->email ?: 'customer@amanisp.net.id',
                    'reminder_time'    => 1,
                ]);

                try {
                    $generateInvoice = $apiInstance->createInvoice($create_invoice_request);

                    InvoiceHomepass::create([
                        'connection_id'        => $member->connection?->area_id ?? 1,
                        'member_id'            => $member->id,
                        'invoice_type'         => 'H',
                        'start_date'           => now()->toDateString(),
                        'due_date'             => $dueDate->toDateString(),
                        'subscription_period'  => $dueDate->format('M Y'),
                        'inv_number'           => $invNumber,
                        'amount'               => $total_amount,
                        'status'               => 'unpaid',
                        'group_id'             => $member->group_id,
                        'payment_url'          => $generateInvoice['invoice_url'],
                    ]);

                    $lastCreatedDate = $dueDate->toDateString();

                    Log::info("✅ Invoice dibuat untuk member {$member->id} periode {$dueDate->format('F Y')}");
                } catch (\Throwable $th) {
                    Log::error("❌ Gagal generate invoice untuk member {$member->id} bulan {$dueDate->format('F Y')}: {$th->getMessage()}");
                }
            }

            // Naik ke bulan berikutnya
            $baseDate->addMonthNoOverflow();
        }

        // Update last_invoice jika ada yang dibuat
        if ($lastCreatedDate) {
            $pd->update(['last_invoice' => $lastCreatedDate]);
            Log::info("📝 Update last_invoice member {$member->id} → {$lastCreatedDate}");
        }
    }
}
