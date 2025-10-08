<?php

namespace App\Http\Controllers;

use App\Models\globalSettings;
use App\Models\InvoiceHomepass;
use App\Models\payout;
use App\Models\WhatsappMessageLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function notifPayment(Request $request)
    {
        $callbackToken = env('XENDIT_WEBHOOK_SECRET');
        $receivedToken = $request->header('x-callback-token');

        // Validasi token callback
        if (!$callbackToken || $receivedToken !== $callbackToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized callback token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            // Cari invoice berdasarkan external_id
            $invoice = InvoiceHomepass::with(['member', 'connection.profile', 'group'])
                ->where('inv_number', $request->external_id)
                ->first();

            if (!$invoice) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invoice tidak ditemukan',
                ], Response::HTTP_NOT_FOUND);
            }

            if ($request->status === 'PAID') {
                // Update invoice
                $invoice->update([
                    'status' => 'paid',
                    'payment_method' => 'payment_gateway',
                    'paid_at' => now(),
                ]);

                // Update saldo Xendit di global settings
                globalSettings::where(function ($query) use ($invoice) {
                    $invoice->group_id
                        ? $query->where('group_id', $invoice->group_id)
                        : $query->whereNull('group_id');
                })->update([
                    'xendit_balance' => DB::raw('xendit_balance + ' . $invoice->amount)
                ]);

                // Kirim notifikasi WhatsApp
                if (!empty($invoice->member->phone_number)) {
                    $ppn = $invoice->pppoe->ppn ?? 0;
                    $discount = $invoice->pppoe->discount ?? 0;
                    $total = $invoice->amount + ($invoice->amount * $ppn / 100) - $discount;

                    // $message = "Salam Bpk/Ibu {$invoice->member->name}\n\n" .
                    //     "*Pembayaran Berhasil!*\n\n" .
                    //     "ID Pelanggan: {$invoice->pppoe->internet_number}\n" .
                    //     "Nomor Invoice: {$invoice->inv_number}\n" .
                    //     "Amount: Rp " . number_format($invoice->amount, 0, ',', '.') . "\n" .
                    //     "PPN: {$ppn}\n" .
                    //     "Discount: {$discount}\n" .
                    //     "Total: Rp " . number_format($total, 0, ',', '.') . "\n" .
                    //     "Item: Internet {$invoice->pppoe->username} - Paket {$invoice->pppoe->profile->name}\n" .
                    //     "Period: {$invoice->subscription_period}\n" .
                    //     "Status: *LUNAS*\n" .
                    //     "Payment Method: Xendit Payment Gateway\n\n" .
                    //     "Terima kasih.\n" .
                    //     "Jika ada pertanyaan silakan hubungi admin.\n" .
                    //     "PT Anugerah Media Data Nusantara - AMAN ISP\n\n" .
                    //     "_Pesan otomatis - mohon tidak membalas_";

                    // Http::withHeaders([
                    //     'x-api-key' => env('WA_API_TOKEN'),
                    // ])->post('https://wa.amanisp.net.id/api/send-message', [
                    //     'sessionId' => $invoice->group->wa_api_token,
                    //     'number' => $invoice->member->phone_number,
                    //     'message' => $message,
                    //     'group_id' => $invoice->group->id,
                    //     'subject' => 'Invoice Paid'
                    // ]);

                    // whatsappMessageLog::create([
                    //     'group_id' => $invoice->group->id,
                    //     'phone' => $invoice->member->phone_number,
                    //     'subject' => 'Payment PAID ' . $invoice->inv_number,
                    //     'message' => $message,
                    //     'session_id' => $invoice->group->wa_api_token,
                    //     'status' => 'sent',
                    //     'sent_at' => now(),
                    // ]);
                }
            } else {
                // Pembayaran dibatalkan atau gagal
                $invoice->update([
                    'status' => 'unpaid',
                    'payment_method' => null,
                    'paid_at' => null
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook diproses',
                'invoice' => $invoice
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function notifPayoutLink(Request $request)
    {
        $callbackToken = env('XENDIT_WEBHOOK_SECRET');
        $receivedToken = $request->header('x-callback-token');

        // Validasi token callback
        if (!$callbackToken || $receivedToken !== $callbackToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized callback token',
            ], Response::HTTP_UNAUTHORIZED);
        }

        Log::info("Payout Link Webhook Received", $request->all());


        try {
            $status = $request->status;
            $referenceId = $request->external_id;

            // Cari berdasarkan reference_id
            $payout = payout::where('external_id', $referenceId)->first();

            if (!$payout) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payout tidak ditemukan berdasarkan reference_id',
                ], Response::HTTP_NOT_FOUND);
            }

            // Update status


            // Jika sukses, kurangi saldo
            if ($status === 'COMPLETED') {
                $payout->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);

                $balance = globalSettings::where('group_id', $payout->group_id)->first();
                if ($balance) {
                    $newBalance = (int) $balance->xendit_balance - (int) $payout->amount;
                    $balance->update(['xendit_balance' => $newBalance]);
                }
            } else if ($status === 'FAILED') {
                $payout->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);
            } else if ($status === 'EXPIRED') {
                $payout->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);
            } else if ($status === 'VOIDED') {
                $payout->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed',
                'data' => $payout,
            ]);
        } catch (\Throwable $e) {
            Log::error("Webhook Error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
