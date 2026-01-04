<?php

namespace App\Http\Controllers;

use App\Models\GlobalSettings;
use App\Models\InvoiceHomepass;
use App\Models\Payout;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    protected $whatsappService;
    public function __construct(WhatsappService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    private function getApiKey($groupId)
    {
        $settings = GlobalSettings::where('group_id', $groupId)->first();
        return $settings->whatsapp_api_key ?? null;
    }

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
                GlobalSettings::where(function ($query) use ($invoice) {
                    $invoice->group_id
                        ? $query->where('group_id', $invoice->group_id)
                        : $query->whereNull('group_id');
                })->update([
                    'xendit_balance' => DB::raw('xendit_balance + ' . $invoice->amount)
                ]);

                // Kirim notifikasi WhatsApp
                if (!empty($invoice->member->phone_number)) {
                    $ppn = $invoice->pppoe->ppn ?? 0;
                    $groupId = $invoice->group_id;
                    $discount = $invoice->pppoe->discount ?? 0;
                    $total = $invoice->amount + ($invoice->amount * $ppn / 100) - $discount;

                    $apiKey = $this->getApiKey($groupId);

                    if (isset($apiKey)) {
                        $footer = GlobalSettings::where('group_id', $groupId)
                            ->value('footer');

                        $this->whatsappService->sendFromTemplate(
                            $apiKey,
                            $invoice->member->phone_number,
                            'payment_paid',
                            [
                                'full_name'   => $invoice->member->fullname,
                                'no_invoice'  => $invoice->inv_number,
                                'total' => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                                'pppoe_user' => $invoice->connection->username,
                                'pppoe_profile' => $invoice->connection->profile->name,
                                'period'    => $invoice->subscription_period,
                                'payment_gateway' => 'PAYMENT GATEWAY',
                                'footer' => $footer
                            ],
                            [
                                'group_id' => $invoice->group_id,
                            ]
                        );
                    }
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
            $payout = Payout::where('external_id', $referenceId)->first();

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

                $balance = GlobalSettings::where('group_id', $payout->group_id)->first();
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
