<?php

namespace App\Jobs;

use App\Helpers\InvoiceHelper;
use App\Models\InvoiceHomepass;
use App\Models\Member;
use App\Models\PaymentDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\FonnteService;

class ManualPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;

    protected int $memberId;
    protected string $paymentMethod;
    protected string $month;
    protected int $userId;

    public function __construct($memberId, $paymentMethod, $month, $userId)
    {
        $this->memberId = $memberId;
        $this->paymentMethod = $paymentMethod;
        $this->month = $month;
        $this->userId = $userId;
    }

    public function handle(FonnteService $fonnte)
    {
        Log::info('ManualPaymentJob START', [
            'member_id' => $this->memberId,
            'month' => $this->month,
            'payment_method' => $this->paymentMethod,
            'user_id' => $this->userId
        ]);

        DB::beginTransaction();

        try {

            $user = User::find($this->userId);

            if (!$user) {
                Log::error('User tidak ditemukan', [
                    'user_id' => $this->userId
                ]);
                DB::rollBack();
                return;
            }

            $startDate = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
            $endDate   = $startDate->copy()->endOfMonth();

            $member = Member::with('paymentDetail', 'connection.profile')
                ->where('id', $this->memberId)
                ->where('billing', 1)
                ->where('group_id', $user->group_id)
                ->first();

            if (!$member) {
                Log::error('Member tidak valid / beda group', [
                    'member_id' => $this->memberId
                ]);
                DB::rollBack();
                return;
            }

            if (!$member->paymentDetail) {
                Log::error('Payment detail kosong', [
                    'member_id' => $member->id
                ]);
                DB::rollBack();
                return;
            }

            // 🔒 Anti double invoice
            $exists = InvoiceHomepass::where('member_id', $member->id)
                ->where('start_date', $startDate->toDateString())
                ->exists();

            if ($exists) {
                Log::warning('Invoice sudah ada (skip)', [
                    'member_id' => $member->id,
                    'start_date' => $startDate->toDateString()
                ]);
                DB::rollBack();
                return;
            }

            $pd = $member->paymentDetail;
            $totalAmount = $pd->amount - ($pd->discount ?? 0);

            $connection = $member->connection;

            if (!$connection) {
                Log::warning('Connection kosong', [
                    'member_id' => $member->id
                ]);
            }

            $areaId = $connection?->area_id ?? 1;

            Log::info('Generate invoice number', [
                'area_id' => $areaId
            ]);

            $invNumber = InvoiceHelper::generateInvoiceNumber($areaId, 'H');

            Log::info('Creating invoice...', [
                'member_id' => $member->id,
                'inv_number' => $invNumber,
                'amount' => $totalAmount
            ]);

            $invoice = InvoiceHomepass::create([
                'connection_id'       => $connection?->id,
                'payer_id'            => $user->id,
                'member_id'           => $member->id,
                'invoice_type'        => 'H',
                'start_date'          => $startDate->toDateString(),
                'due_date'            => $endDate->toDateString(),
                'subscription_period' => $startDate->translatedFormat('F Y'),
                'inv_number'          => $invNumber,
                'amount'              => $totalAmount,
                'payment_method'      => $this->paymentMethod,
                'status'              => 'paid',
                'paid_at'             => now(),
                'group_id'            => $member->group_id,
                'payment_url'         =>  'https://bayar.amanisp.net.id',
            ]);

            PaymentDetail::where('id', $member->payment_detail_id)
                ->update(['last_invoice' => $startDate->toDateString()]);

            DB::commit();

            Log::info('Invoice berhasil dibuat', [
                'invoice_id' => $invoice->id
            ]);


            // =========================
            // 🚀 Kirim WhatsApp (Controller Style)
            // =========================
            if (!empty($member->phone_number) && str_starts_with($member->phone_number, '62')) {

                $delaySeconds = rand(3, 8);

                Log::info('Delay sebelum kirim WA', [
                    'delay_seconds' => $delaySeconds,
                    'member_id' => $member->id
                ]);

                sleep($delaySeconds);

                try {

                    Log::info('Kirim WhatsApp', [
                        'phone' => $member->phone_number,
                        'invoice_id' => $invoice->id
                    ]);

                    $fonnte->sendText(
                        $user->group_id,
                        $member->phone_number,
                        [
                            'template' => 'payment_paid',
                            'variables' => [
                                'full_name'        => $member->fullname,
                                'no_invoice'       => $invoice->inv_number,
                                'total'            => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                                'pppoe_user'       => $connection?->username,
                                'pppoe_profile'    => $connection?->profile->name,
                                'period'           => $invoice->subscription_period,
                                'payment_gateway'  => $this->paymentMethod === 'bank_transfer'
                                    ? 'Transfer Bank'
                                    : 'Cash',
                                'footer'           => 'PT. Anugerah Media Data Nusantara'
                            ]
                        ]
                    );

                    Log::info('WhatsApp berhasil dikirim', [
                        'invoice_id' => $invoice->id
                    ]);
                } catch (\Throwable $waError) {

                    Log::error('Gagal kirim WhatsApp', [
                        'invoice_id' => $invoice->id,
                        'error' => $waError->getMessage()
                    ]);
                }
            } else {

                Log::warning('Nomor tidak valid / tidak dikirim WA', [
                    'member_id' => $member->id,
                    'phone' => $member->phone_number
                ]);
            }

            Log::info('ManualPaymentJob DONE', [
                'member_id' => $member->id
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('ManualPaymentJob FAILED', [
                'member_id' => $this->memberId,
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // supaya retry tetap jalan
        }
    }
}
