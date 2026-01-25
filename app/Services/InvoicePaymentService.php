<?php

namespace App\Services;

use App\Models\InvoiceHomepass;
use App\Jobs\SendInvoicePaymentNotification;
use Illuminate\Support\Facades\Log;

/**
 * Invoice Payment Notification Service
 *
 * Coordinate WhatsApp notification ketika invoice dibayar
 * Dispatch job ke queue untuk async processing
 */
class InvoicePaymentService
{
    /**
     * Send payment notification via WhatsApp
     * Called setelah successful payment
     *
     * @param int $invoiceId
     * @param string|null $apiKey
     * @return array
     */
    public function sendPaymentNotification($invoiceId, $apiKey = null)
    {
        try {
            // 1. Get invoice dengan relations
            $invoice = InvoiceHomepass::with([
                'member.paymentDetail',
                'member.connection.profile'
            ])->findOrFail($invoiceId);

            // 2. Validate invoice is paid
            if ($invoice->status !== 'paid') {
                Log::warning('Invoice not paid', [
                    'invoice_id' => $invoiceId,
                    'status' => $invoice->status,
                ]);
                return [
                    'success' => false,
                    'message' => 'Invoice belum dibayar',
                ];
            }

            // 3. Validate member & phone
            if (!$invoice->member) {
                Log::error('Member not found', ['invoice_id' => $invoiceId]);
                return [
                    'success' => false,
                    'message' => 'Data pelanggan tidak ditemukan',
                ];
            }

            if (!$invoice->member->phone_number) {
                Log::error('Member phone not found', [
                    'invoice_id' => $invoiceId,
                    'member_id' => $invoice->member_id,
                ]);
                return [
                    'success' => false,
                    'message' => 'Nomor telepon pelanggan tidak ditemukan',
                ];
            }

            // 4. Mark as pending
            $invoice->update([
                'wa_status' => 'pending',
                'wa_error_message' => null,
            ]);

            // 5. Dispatch job
            $actualApiKey = $apiKey ?? config('services.mpwa.api_key');

            SendInvoicePaymentNotification::dispatch($invoice, $actualApiKey)
                ->onQueue('default')
                ->delay(now()->addSeconds(2));

            Log::info('WhatsApp notification queued', [
                'invoice_id' => $invoiceId,
                'inv_number' => $invoice->inv_number,
            ]);

            return [
                'success' => true,
                'message' => 'Notifikasi WhatsApp sedang diproses',
                'wa_status' => 'pending',
            ];

        } catch (\Exception $e) {
            Log::error('InvoicePaymentService error', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal memproses notifikasi: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Resend notification (manual retry)
     *
     * @param int $invoiceId
     * @return array
     */
    public function resendNotification($invoiceId)
    {
        try {
            $invoice = InvoiceHomepass::findOrFail($invoiceId);

            if ($invoice->wa_status === 'failed_permanently') {
                return [
                    'success' => false,
                    'message' => 'Gagal permanen. Hubungi customer service.',
                ];
            }

            if ($invoice->wa_status === 'sent') {
                return [
                    'success' => false,
                    'message' => 'Notifikasi sudah terkirim sebelumnya',
                ];
            }

            // Reset & resend
            $invoice->update([
                'wa_status' => 'pending',
                'wa_error_message' => null,
            ]);

            $apiKey = config('services.mpwa.api_key');
            SendInvoicePaymentNotification::dispatch($invoice, $apiKey)
                ->onQueue('default')
                ->delay(now()->addSeconds(1));

            Log::info('Invoice notification resend queued', [
                'invoice_id' => $invoiceId,
            ]);

            return [
                'success' => true,
                'message' => 'Notifikasi sedang dikirim ulang',
                'wa_status' => 'pending',
            ];

        } catch (\Exception $e) {
            Log::error('Resend error', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check notification status
     *
     * @param int $invoiceId
     * @return array
     */
    public function checkNotificationStatus($invoiceId)
    {
        try {
            $invoice = InvoiceHomepass::findOrFail($invoiceId);

            return [
                'success' => true,
                'data' => [
                    'wa_status' => $invoice->wa_status,
                    'wa_sent_at' => $invoice->wa_sent_at,
                    'wa_error_message' => $invoice->wa_error_message,
                    'can_retry' => $invoice->wa_status === 'failed',
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
