<?php

namespace App\Events;

use App\Models\InvoiceHomepass;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public InvoiceHomepass $invoice;
    public string $newStatus;
    public string $oldStatus;
    public ?string $paymentMethod;

    /**
     * Create a new event instance.
     *
     * @param InvoiceHomepass $invoice
     * @param string $newStatus (paid, unpaid, pending, overdue)
     * @param string $oldStatus
     * @param string|null $paymentMethod
     */
    public function __construct(
        InvoiceHomepass $invoice,
        string $newStatus,
        string $oldStatus = 'unpaid',
        ?string $paymentMethod = null
    ) {
        $this->invoice = $invoice;
        $this->newStatus = $newStatus;
        $this->oldStatus = $oldStatus;
        $this->paymentMethod = $paymentMethod;
    }
}
