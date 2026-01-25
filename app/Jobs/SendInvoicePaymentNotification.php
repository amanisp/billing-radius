<?php

namespace App\Jobs;

use App\Models\InvoiceHomepass;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendInvoicePaymentNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $invoiceId;
    protected $apiKey;
    protected $attempts = 3;
    public $timeout = 300; // 5 minutes
    public $backoff = [60, 300, 600]; // 1 min, 5 min, 10 min exponential backoff

    /**
     * Create a new job instance.
     */
    public function __construct($invoice, $apiKey)
    {
        $this->invoiceId = $invoice->id;
        $this->apiKey = $apiKey;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            // Get invoice dengan relation
            $invoice = InvoiceHomepass::with([
                'member.paymentDetail',
                'member.connection.profile'
            ])->findOrFail($this->invoiceId);

            // Check if already sent
            if ($invoice->wa_status === 'sent') {
                Log::info('Invoice WA already sent', [
                    'invoice_id' => $invoice->id,
                    'inv_number' => $invoice->inv_number,
                ]);
                return true;
            }

            // Check if permanently failed
            if ($invoice->wa_status === 'failed_permanently') {
                Log::warning('Invoice WA marked as permanently failed', [
                    'invoice_id' => $invoice->id,
                    'inv_number' => $invoice->inv_number,
                ]);
                return true;
            }

            // Validate phone number
            if (!$invoice->member || !$invoice->member->phone_number) {
                $this->updateInvoiceStatus(
                    $invoice,
                    'failed_permanently',
                    'Nomor telepon member tidak ditemukan'
                );
                return false;
            }

            // Format phone number
            $phoneNumber = $this->formatPhoneNumber($invoice->member->phone_number);

            // Build message
            $message = $this->buildMessage($invoice);

            // Send via MPWA
            $response = $this->sendViaWA($phoneNumber, $message);

            // Check response
            if ($response['success']) {
                // Mark as sent
                $this->updateInvoiceStatus(
                    $invoice,
                    'sent',
                    null
                );

                Log::info('Invoice WA sent successfully', [
                    'invoice_id' => $invoice->id,
                    'inv_number' => $invoice->inv_number,
                    'phone' => $this->maskPhone($phoneNumber),
                ]);

                return true;
            } else {
                // Mark as failed (will retry)
                $this->updateInvoiceStatus(
                    $invoice,
                    'failed',
                    $response['error']
                );

                Log::warning('Invoice WA send failed', [
                    'invoice_id' => $invoice->id,
                    'inv_number' => $invoice->inv_number,
                    'error' => $response['error'],
                    'attempt' => $this->attempts - $this->attempts,
                ]);

                // Throw to trigger retry
                throw new \Exception($response['error']);
            }

        } catch (\Exception $e) {
            Log::error('Invoice notification job error', [
                'invoice_id' => $this->invoiceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Increment attempts
            if ($this->attempts <= 1) {
                // Mark as permanently failed after 3 retries
                $invoice = InvoiceHomepass::find($this->invoiceId);
                if ($invoice) {
                    $this->updateInvoiceStatus(
                        $invoice,
                        'failed_permanently',
                        'Gagal setelah 3 kali percobaan: ' . $e->getMessage()
                    );
                }
                return false;
            }

            // Retry
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception)
    {
        Log::error('Invoice notification job failed permanently', [
            'invoice_id' => $this->invoiceId,
            'error' => $exception->getMessage(),
        ]);

        $invoice = InvoiceHomepass::find($this->invoiceId);
        if ($invoice) {
            $this->updateInvoiceStatus(
                $invoice,
                'failed_permanently',
                'Gagal permanen setelah 3 kali retry'
            );
        }
    }

    /**
     * Build WhatsApp message
     */
    private function buildMessage($invoice)
    {
        $member = $invoice->member;
        $connection = $member->connection;
        $profile = $connection->profile;
        $paymentDetail = $member->paymentDetail;

        $fullName = $member->full_name ?? 'Pelanggan';
        $internetNumber = $connection->internet_number ?? '-';
        $amount = number_format($invoice->amount, 0, ',', '.');
        $period = $invoice->subscription_period ?? '-';
        $dueDate = $invoice->due_date ?
            Carbon::parse($invoice->due_date)->translatedFormat('d F Y') : '-';

        // Format message
        $message = "Assalamu'alaikum *{$fullName}* 🙏\n\n";
        $message .= "Invoice pembayaran Anda sudah kami terima.\n";
        $message .= "Terima kasih telah melakukan pembayaran.\n\n";
        $message .= "📋 *Detail Pembayaran:*\n";
        $message .= "Nomor Internet: *{$internetNumber}*\n";
        $message .= "Periode: *{$period}*\n";
        $message .= "Nominal: *Rp {$amount}*\n";
        $message .= "Invoice: *{$invoice->inv_number}*\n\n";
        $message .= "Terima kasih telah menjadi pelanggan setia kami.\n";
        $message .= "Jika ada pertanyaan, silahkan hubungi customer service kami.\n\n";
        $message .= "Salam hormat,\n";
        $message .= "*AMAN ISP* 📡\n";

        return $message;
    }

    /**
     * Send message via MPWA WhatsApp gateway
     */
    private function sendViaWA($phoneNumber, $message)
    {
        try {
            $baseUrl = config('services.mpwa.base_url', 'https://mpwa.amanisp.net.id');
            $senderNumber = config('services.mpwa.sender');

            $response = Http::timeout(30)
                ->post("{$baseUrl}/send-message", [
                    'apikey' => $this->apiKey,
                    'sender' => $senderNumber,
                    'number' => $phoneNumber,
                    'message' => $message,
                    'footer' => 'Sent via AMAN ISP Billing System',
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['status']) && $data['status'] === true) {
                return [
                    'success' => true,
                    'message' => 'Message sent successfully',
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $data['msg'] ?? 'Unknown error from WhatsApp gateway',
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'HTTP Request Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update invoice WA status
     */
    private function updateInvoiceStatus($invoice, $status, $errorMessage)
    {
        $invoice->update([
            'wa_status' => $status,
            'wa_sent_at' => ($status === 'sent') ? Carbon::now() : $invoice->wa_sent_at,
            'wa_error_message' => $errorMessage,
        ]);
    }

    /**
     * Format phone number ke format WhatsApp
     * Input: 081234567890 atau +6281234567890
     * Output: 6281234567890
     */
    private function formatPhoneNumber($phone)
    {
        // Remove spaces and special characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading 0 and add 62
        if (substr($phone, 0, 1) === '0') {
            $phone = '62' . substr($phone, 1);
        }

        // Ensure starts with 62
        if (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }

    /**
     * Mask phone number for logging
     */
    private function maskPhone($phone)
    {
        return substr($phone, 0, 4) . '****' . substr($phone, -2);
    }
}
