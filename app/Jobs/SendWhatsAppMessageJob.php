<?php

namespace App\Jobs;

use App\Models\InvoiceHomepass;
use App\Models\Member;
use App\Models\ActivityLog;
use App\Services\WhatsappNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class SendWhatsappMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public InvoiceHomepass $invoice;
    public Member $member;
    public string $status;
    public ?string $paymentMethod;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        InvoiceHomepass $invoice,
        Member $member,
        string $status,
        ?string $paymentMethod = null
    ) {
        $this->invoice = $invoice;
        $this->member = $member;
        $this->status = $status;
        $this->paymentMethod = $paymentMethod;

        // Set queue name
        $this->onQueue('whatsapp');
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsappNotificationService $service): void
    {
        try {
            Log::info('Processing WhatsApp notification job', [
                'invoice_id' => $this->invoice->id,
                'member_id' => $this->member->id,
                'status' => $this->status,
                'attempt' => $this->attempts()
            ]);

            // Send WhatsApp notification
            $result = $service->sendPaymentNotification(
                $this->invoice,
                $this->member,
                $this->status,
                $this->paymentMethod
            );

            if ($result['success']) {
                // Log success to activity_logs
                ActivityLog::create([
                    'operation' => 'MPWA_MESSAGE_' . strtoupper($this->status),
                    'table_name' => 'whatsapp_messages',
                    'username' => 'System',
                    'role' => 'queue_worker',
                    'ip_address' => '127.0.0.1',
                    'details' => json_encode([
                        'invoice_id' => $this->invoice->id,
                        'invoice_number' => $this->invoice->inv_number,
                        'member_id' => $this->member->id,
                        'member_name' => $this->member->fullname,
                        'phone' => $this->member->phone_number,
                        'status' => $this->status,
                        'message_id' => $result['message_id'] ?? null,
                        'success' => true
                    ]),
                ]);

                Log::info('WhatsApp notification sent successfully', [
                    'invoice_id' => $this->invoice->id,
                    'message_id' => $result['message_id'] ?? null
                ]);
            } else {
                throw new Exception($result['error'] ?? 'Failed to send WhatsApp message');
            }

        } catch (Exception $e) {
            // Log failure
            Log::error('WhatsApp notification job failed', [
                'invoice_id' => $this->invoice->id,
                'member_id' => $this->member->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);

            // Log to activity_logs
            ActivityLog::create([
                'operation' => 'MPWA_MESSAGE_FAILED',
                'table_name' => 'whatsapp_messages',
                'username' => 'System',
                'role' => 'queue_worker',
                'ip_address' => '127.0.0.1',
                'details' => json_encode([
                    'invoice_id' => $this->invoice->id,
                    'member_id' => $this->member->id,
                    'phone' => $this->member->phone_number,
                    'status' => $this->status,
                    'error' => $e->getMessage(),
                    'attempt' => $this->attempts()
                ]),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::critical('WhatsApp notification job permanently failed', [
            'invoice_id' => $this->invoice->id,
            'member_id' => $this->member->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Log permanent failure
        ActivityLog::create([
            'operation' => 'MPWA_MESSAGE_PERMANENT_FAILURE',
            'table_name' => 'whatsapp_messages',
            'username' => 'System',
            'role' => 'queue_worker',
            'ip_address' => '127.0.0.1',
            'details' => json_encode([
                'invoice_id' => $this->invoice->id,
                'invoice_number' => $this->invoice->inv_number,
                'member_id' => $this->member->id,
                'member_name' => $this->member->fullname,
                'phone' => $this->member->phone_number,
                'status' => $this->status,
                'error' => $exception->getMessage(),
                'max_attempts_reached' => true
            ]),
        ]);
    }
}
