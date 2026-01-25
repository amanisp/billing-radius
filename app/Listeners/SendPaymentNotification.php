<?php

namespace App\Listeners;

use App\Events\PaymentStatusChanged;
use App\Jobs\SendWhatsappMessageJob;
use Illuminate\Support\Facades\Log;

class SendPaymentNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentStatusChanged $event): void
    {
        try {
            // Ambil data dari event
            $invoice = $event->invoice;
            $newStatus = $event->newStatus;
            $paymentMethod = $event->paymentMethod;

            // Load member relationship
            $member = $invoice->member;

            // Validasi: pastikan member ada dan punya nomor telepon
            if (!$member) {
                Log::warning('Invoice has no member', [
                    'invoice_id' => $invoice->id
                ]);
                return;
            }

            if (empty($member->phone_number)) {
                Log::warning('Member has no phone number', [
                    'invoice_id' => $invoice->id,
                    'member_id' => $member->id
                ]);
                return;
            }

            // Log untuk debugging
            Log::info('Dispatching WhatsApp notification job', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->inv_number,
                'member_id' => $member->id,
                'member_name' => $member->fullname,
                'phone' => $member->phone_number,
                'status' => $newStatus,
                'payment_method' => $paymentMethod
            ]);

            // Dispatch queue job untuk kirim WhatsApp
            SendWhatsappMessageJob::dispatch(
                $invoice,
                $member,
                $newStatus,
                $paymentMethod
            )->delay(now()->addSeconds(5)); // delay 5 detik untuk stabilitas

        } catch (\Exception $e) {
            Log::error('Failed to dispatch WhatsApp notification job', [
                'invoice_id' => $event->invoice->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }
}
