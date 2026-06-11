<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Member;
use App\Models\GlobalSettings;
use App\Helpers\InvoiceHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Generate invoice manual / bulk
     */
    public function createInvoices(array $data): array
    {
        return DB::transaction(function () use ($data) {

            $member = Member::with(['paymentDetail', 'connection'])
                ->findOrFail($data['member_id']);

            $paymentDetail = $member->paymentDetail;

            if (!$paymentDetail) {
                throw new \Exception(
                    'Member tidak memiliki payment detail.'
                );
            }

            /**
             * =========================================
             * KALKULASI HARGA DARI PAYMENT DETAIL
             * =========================================
             */
            $baseAmount = (float) $paymentDetail->amount;
            $discount   = (float) ($paymentDetail->discount ?? 0);
            $ppn        = (float) ($paymentDetail->ppn ?? 0);

            /**
             * Asumsi: PPN berbentuk nominal. 
             * Jika PPN di database berbentuk persentase (misal 11 untuk 11%), 
             * silakan ganti dengan:
             * $ppnNominal = ($baseAmount - $discount) * ($ppn / 100);
             * $finalAmount = $baseAmount - $discount + $ppnNominal;
             */
            $finalAmount = $baseAmount - $discount + $ppn;

            /**
             * Global settings
             */
            $settings = GlobalSettings::where(
                'group_id',
                $member->group_id
            )->first();

            if (!$settings) {
                throw new \Exception(
                    'Global settings tidak ditemukan.'
                );
            }

            $subscriptionPeriod = (int) $data['subscription_period'];

            if ($subscriptionPeriod < 1) {
                throw new \Exception(
                    'Subscription period minimal 1.'
                );
            }

            /**
             * =========================================
             * START DATE
             * =========================================
             */
            $invoiceGenerateDay =
                (int) ($settings->invoice_generate_days ?? 1);

            if (!empty($data['start_month_year'])) {
                $currentStartDate = Carbon::createFromFormat(
                    'Y-m',
                    $data['start_month_year']
                )
                    ->startOfMonth()
                    ->day($invoiceGenerateDay);
            } else {
                $currentStartDate = now()
                    ->startOfMonth()
                    ->day($invoiceGenerateDay);
            }

            /**
             * Handle overflow tanggal
             */
            if ($currentStartDate->day !== $invoiceGenerateDay) {
                $currentStartDate->endOfMonth();
            }

            $createdInvoices = [];

            for ($i = 0; $i < $subscriptionPeriod; $i++) {

                $startDate = $currentStartDate->copy();
                $areaId = $member->connection?->area_id
                    ?? $member->area_id
                    ?? 0;

                if (!$areaId) {
                    throw new \Exception(
                        "Member {$member->id} tidak memiliki area koneksi."
                    );
                }

                /**
                 * =========================================
                 * VALIDASI DUPLICATE
                 * =========================================
                 */
                $exists = Invoice::where(
                    'member_id',
                    $member->id
                )
                    ->whereYear('start_date', $startDate->year)
                    ->whereMonth('start_date', $startDate->month)
                    ->exists();

                if ($exists) {
                    throw new \Exception(
                        "Invoice bulan {$startDate->translatedFormat('F Y')} sudah ada."
                    );
                }

                $dueDay = (int) ($settings->due_date_pascabayar ?? 10);

                /**
                 * due date mengikuti bulan invoice
                 */
                $dueDate = Carbon::create(
                    $startDate->year,
                    $startDate->month,
                    1
                )->day($dueDay);

                /**
                 * overflow
                 */
                if ($dueDate->month !== $startDate->month) {
                    $dueDate = $startDate->copy()->endOfMonth();
                }

                /**
                 * =========================================
                 * CREATE INVOICE
                 * =========================================
                 */
                $invoice = Invoice::create([

                    'member_id'           => $member->id,
                    'connection_id'       => $member->connection_id,
                    'payer_id'            => $member->user_id,
                    'invoice_type'        => 'H',
                    'start_date'          => $startDate->toDateString(),
                    'due_date'            => $dueDate->toDateString(),
                    'subscription_period' => 1,
                    'inv_number'          => InvoiceHelper::generateInvoiceNumber(
                        $member->group_id,
                        $areaId,
                        'H',
                        Invoice::class,
                        $startDate
                    ),

                    // Gunakan hasil kalkulasi dari payment detail
                    'amount'              => $finalAmount,

                    // Jika tabel invoices Anda juga menyimpan riwayat diskon & ppn,
                    // uncomment bagian di bawah ini:
                    // 'subtotal'            => $baseAmount,
                    // 'discount'            => $discount,
                    // 'ppn'                 => $ppn,

                    'status'              => 'unpaid',
                    'payment_url'         => 'default',
                    'group_id'            => $member->group_id,
                ]);

                $createdInvoices[] = $invoice;

                /**
                 * =========================================
                 * NEXT MONTH
                 * =========================================
                 */
                $currentStartDate
                    ->addMonthNoOverflow()
                    ->day($invoiceGenerateDay);

                if ($currentStartDate->day !== $invoiceGenerateDay) {
                    $currentStartDate->endOfMonth();
                }
            }

            /**
             * update last invoice
             */
            $lastInvoice = end($createdInvoices);

            $paymentDetail->updateLastInvoice(
                $lastInvoice->start_date
            );

            return $createdInvoices;
        });
    }
}
