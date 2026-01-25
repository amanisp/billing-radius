<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\InvoiceHomepass;
use App\Models\Member;
use Exception;

class WhatsappNotificationService
{
    private string $apiKey;
    private string $baseUrl;
    private string $defaultSender;

    public function __construct()
    {
        $this->apiKey = config('services.mpwa.api_key');
        $this->baseUrl = config('services.mpwa.base_url');
        $this->defaultSender = config('services.mpwa.sender');
    }

    /**
     * Send WhatsApp message untuk payment notification
     */
    public function sendPaymentNotification(
        InvoiceHomepass $invoice,
        Member $member,
        string $status,
        ?string $paymentMethod = null
    ): array {
        try {
            // Normalize phone number
            $phoneNumber = $this->normalizePhoneNumber($member->phone_number);

            if (!$this->isValidWhatsAppNumber($phoneNumber)) {
                throw new Exception("Invalid WhatsApp number: {$phoneNumber}");
            }

            // Get message template based on status
            $message = $this->getMessageTemplate($status, $invoice, $member, $paymentMethod);

            // Send via MPWA API
            return $this->sendMessage($phoneNumber, $message);

        } catch (Exception $e) {
            Log::error('WhatsApp Notification Failed', [
                'invoice_id' => $invoice->id,
                'member_id' => $member->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Normalize phone number ke format WhatsApp
     */
    private function normalizePhoneNumber(?string $phone): string
    {
        if (empty($phone)) {
            throw new Exception('Phone number is empty');
        }

        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Convert 08XX to 628XX
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        // Add 62 prefix if starts with 8
        if (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }

    /**
     * Validate WhatsApp number format
     */
    private function isValidWhatsAppNumber(string $phone): bool
    {
        // Must start with 62 and be 10-15 digits total
        return preg_match('/^62\d{8,13}$/', $phone);
    }

    /**
     * Get message template based on payment status
     */
    private function getMessageTemplate(
        string $status,
        InvoiceHomepass $invoice,
        Member $member,
        ?string $paymentMethod
    ): string {
        $memberName = $member->fullname ?? 'Pelanggan';
        $invoiceNumber = $invoice->inv_number;
        $amount = 'Rp ' . number_format($invoice->amount, 0, ',', '.');
        $dueDate = $invoice->due_date->format('d/m/Y');
        $period = $invoice->subscription_period;

        $paymentMethodText = match($paymentMethod) {
            'cash' => 'Tunai',
            'bank_transfer' => 'Transfer Bank',
            'payment_gateway' => 'Payment Gateway',
            default => 'Lainnya'
        };

        return match($status) {
            'paid' =>
                "Halo *{$memberName}*,\n\n" .
                "✅ *Pembayaran Berhasil Diterima*\n\n" .
                "Terima kasih! Pembayaran Anda telah kami terima.\n\n" .
                "📋 *Detail Pembayaran:*\n" .
                "• No. Invoice: {$invoiceNumber}\n" .
                "• Jumlah: {$amount}\n" .
                "• Metode: {$paymentMethodText}\n" .
                "• Periode: {$period}\n" .
                "• Tanggal: " . now()->format('d/m/Y H:i') . "\n\n" .
                "🌐 *Layanan Anda sudah aktif kembali.*\n\n" .
                "Terima kasih telah menggunakan layanan kami.\n\n" .
                "Salam,\n" .
                "_Admin Aman ISP_",

            'unpaid' =>
                "Halo *{$memberName}*,\n\n" .
                "📌 *Pengingat Tagihan*\n\n" .
                "Anda memiliki tagihan yang belum dibayar:\n\n" .
                "📋 *Detail Tagihan:*\n" .
                "• No. Invoice: {$invoiceNumber}\n" .
                "• Jumlah: {$amount}\n" .
                "• Jatuh Tempo: {$dueDate}\n" .
                "• Periode: {$period}\n\n" .
                "💳 *Silakan lakukan pembayaran sebelum jatuh tempo.*\n\n" .
                "🔗 Link Pembayaran:\n" .
                "{$invoice->payment_url}\n\n" .
                "Hubungi kami jika ada pertanyaan.\n\n" .
                "Terima kasih,\n" .
                "_Admin Aman ISP_",

            'overdue' =>
                "⚠️ *PERHATIAN - Tagihan Jatuh Tempo*\n\n" .
                "Halo *{$memberName}*,\n\n" .
                "Tagihan Anda sudah melewati jatuh tempo.\n\n" .
                "📋 *Detail Tagihan:*\n" .
                "• No. Invoice: {$invoiceNumber}\n" .
                "• Jumlah: {$amount}\n" .
                "• Jatuh Tempo: {$dueDate}\n" .
                "• Periode: {$period}\n\n" .
                "🚨 *Layanan Anda akan segera diputus jika tidak segera dibayar.*\n\n" .
                "Segera lakukan pembayaran atau hubungi customer service kami.\n\n" .
                "🔗 Bayar Sekarang:\n" .
                "{$invoice->payment_url}\n\n" .
                "_Admin Aman ISP_",

            'pending' =>
                "Halo *{$memberName}*,\n\n" .
                "⏳ *Pembayaran Sedang Diproses*\n\n" .
                "Kami telah menerima pembayaran Anda.\n\n" .
                "📋 *Detail:*\n" .
                "• No. Invoice: {$invoiceNumber}\n" .
                "• Jumlah: {$amount}\n" .
                "• Status: Pending\n\n" .
                "Pembayaran Anda sedang dalam verifikasi. \n" .
                "Kami akan menginformasikan jika sudah dikonfirmasi.\n\n" .
                "Terima kasih,\n" .
                "_Admin Aman ISP_",

            default => throw new Exception("Unknown status: {$status}")
        };
    }

    /**
     * Send message via MPWA API
     */
    private function sendMessage(string $phoneNumber, string $message): array
    {
        try {
            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/send-message", [
                    'api_key' => $this->apiKey,
                    'sender' => $this->defaultSender,
                    'number' => $phoneNumber,
                    'message' => $message
                ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('WhatsApp message sent successfully', [
                    'phone' => $phoneNumber,
                    'response' => $data
                ]);

                return [
                    'success' => true,
                    'data' => $data,
                    'message_id' => $data['id'] ?? $data['message_id'] ?? null
                ];
            }

            throw new Exception(
                'MPWA API error: ' . ($response->json()['message'] ?? $response->body())
            );

        } catch (Exception $e) {
            Log::error('MPWA API request failed', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send test message (for testing only)
     */
    public function sendTestMessage(int $groupId, string $phoneNumber): array
    {
        try {
            $phoneNumber = $this->normalizePhoneNumber($phoneNumber);

            if (!$this->isValidWhatsAppNumber($phoneNumber)) {
                throw new Exception("Invalid WhatsApp number: {$phoneNumber}");
            }

            $message =
                "🔔 *Test Notification*\n\n" .
                "Ini adalah test message dari WhatsApp Notification Service.\n\n" .
                "Group ID: {$groupId}\n" .
                "Time: " . now()->format('d/m/Y H:i:s') . "\n\n" .
                "Jika Anda menerima pesan ini, berarti sistem notifikasi WhatsApp berfungsi dengan baik.\n\n" .
                "_Aman ISP - Notification System_";

            return $this->sendMessage($phoneNumber, $message);

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
