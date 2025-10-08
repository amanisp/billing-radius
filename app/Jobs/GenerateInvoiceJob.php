<?php

namespace App\Jobs;

use App\Helpers\InvoiceHelper;
use App\Mail\InvoiceCreatedMail;
use App\Models\invoice;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;
use Xendit\Configuration;

class GenerateInvoiceJob implements ShouldQueue
{
    use Queueable;
    protected $customer;

    /**
     * Create a new job instance.
     */
    public function __construct($customer)
    {
        $this->customer = $customer;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $customer = $this->customer;
        $invoiceDate = Carbon::now();
        $dueDate = $invoiceDate->copy()->endOfMonth();

        // ✅ Cek apakah sudah ada invoice untuk bulan & tahun ini
        $existing = Invoice::where('payer_id', $customer->id)
            ->whereMonth('invoice_date', $invoiceDate->month)
            ->whereYear('invoice_date', $invoiceDate->year)
            ->first();

        if ($existing) {
            Log::info("Invoice sudah ada untuk {$customer->email} - dilewati.");
            return;
        }

        try {
            Configuration::setXenditKey(env('XENDIT_SECRET_KEY'));
            $apiInstance = new InvoiceApi();

            $invNumber = InvoiceHelper::generateInvoiceNumber($customer->area_id, $customer->segmentasi);
            $invoiceDuration = intval($invoiceDate->diffInSeconds($dueDate));

            $create_invoice_request = new CreateInvoiceRequest([
                'external_id' => $invNumber,
                'description' => 'Pembayaran Internet ' . $customer->capacity . 'Mbps',
                'amount' => $customer->price,
                'payer_email' => $customer->email,
                'invoice_duration' => $invoiceDuration,
                'currency' => 'IDR',
                'reminder_time' => 1,
            ]);

            $generateInvoice = $apiInstance->createInvoice($create_invoice_request);

            $datas = Invoice::create([
                'payer_id'      => $customer->id,
                'invoice_type'  => $customer->segmentasi,
                'invoice_date'  => $invoiceDate,
                'due_date'      => $dueDate,
                'subs_period'   => 'superadmin',
                'inv_number'    => $invNumber,
                'payment_url'   => $generateInvoice['invoice_url'] ?? null,
                'amount'        => $customer->price,
                'status'        => 'unpaid',
                'group_id'      => null,
            ]);

            $invoice = invoice::with('payer')->findOrFail($datas->id);

            Mail::to($customer->email)->queue(new InvoiceCreatedMail($invoice));
        } catch (\Throwable $e) {
            Log::error("Error generating invoice untuk {$customer->email}: " . $e->getMessage());
        }
    }
}
