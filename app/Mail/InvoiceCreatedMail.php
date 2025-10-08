<?php

namespace App\Mail;

use App\Models\invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Mail\Mailables\Attachment;


class InvoiceCreatedMail extends Mailable
{
    use Queueable, SerializesModels;
    public invoice $invoice;

    /**
     * Create a new message instance.
     */
    public function __construct(invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invoice Payment Reminder',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.invoice',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $pdfUrl = route('billing.invoicePdf', ['id' => $this->invoice->inv_number]); // pastikan route tersedia

        $response = Http::get($pdfUrl);

        if ($response->successful()) {
            return [
                Attachment::fromData(fn() => $response->body(), 'Invoice-' . $this->invoice->inv_number . '.pdf')
                    ->withMime('application/pdf')
            ];
        }

        return [];
    }
}
