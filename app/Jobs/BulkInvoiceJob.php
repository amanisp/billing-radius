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

    public array $bulkPayload;

    /**
     * Create a new job instance.
     */
    public function __construct(array $bulkPayload)
    {
        $this->bulkPayload = $bulkPayload;
    }

    /**
     * Execute the job.
     */
    public function handle(InvoiceService $invoiceService): void
    {
        foreach ($this->bulkPayload as $payload) {

            try {

                $invoiceService->createInvoices($payload);
            } catch (\Throwable $th) {

                Log::error('Bulk invoice failed', [
                    'member_id' => $payload['member_id'],
                    'message'   => $th->getMessage(),
                ]);
            }
        }
    }
}
