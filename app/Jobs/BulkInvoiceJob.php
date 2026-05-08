<?php

namespace App\Jobs;

use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $payload; // Ubah penamaan agar lebih jelas

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(InvoiceService $invoiceService): void
    {
        // ✅ HAPUS FOREACH. Langsung proses 1 payload.
        try {
            $invoiceService->createInvoices($this->payload);
        } catch (\Throwable $th) {
            Log::error('Bulk invoice failed', [
                'member_id' => $this->payload['member_id'],
                'message'   => $th->getMessage(),
            ]);
        }
    }
}
