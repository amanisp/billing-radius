<?php

namespace App\Jobs;

use App\Helpers\InvoiceHelper;
use App\Models\Connection;
use App\Models\GlobalSettings;
use App\Models\InvoiceHomepass;
use App\Models\PaymentDetail;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;

class GenerateMonthlyInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $whatsappService;

    public function __construct(WhatsappService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
        Configuration::setXenditKey(env('XENDIT_SECRET_KEY'));
    }

    private function getApiKey($groupId)
    {
        return GlobalSettings::where('group_id', $groupId)->value('whatsapp_api_key');
    }

    public function handle(): void
    {
        Log::info('🚀 Memulai GenerateMonthlyInvoiceJob...');
        try {
            $settings = GlobalSettings::all();
            $today = Carbon::today();

            foreach ($settings as $setting) {
                $groupId       = $setting->group_id;
                $generateDays  = $setting->invoice_generate_days ?? 7;
                $pascabayarDay = $setting->due_date_pascabayar ?? 10;

                $connections = Connection::with(['member.PaymentDetail', 'profile', 'area'])
                    ->where('group_id', $groupId)
                    ->whereHas('member', fn($q) => $q->where('billing', 1))
                    ->get()
                    ->filter(function ($conn) use ($today, $generateDays, $pascabayarDay) {
                        $detail = $conn->member->paymentDetail;
                        if (!$detail) {
                            Log::warning("❌ {$conn->member->full_name} tidak punya PaymentDetail, skip");
                            return false;
                        }

                        if ($detail->payment_type === 'prabayar') {
                            $activeDate = Carbon::create(
                                $today->year,
                                $today->month,
                                Carbon::parse($detail->active_date)->day
                            );
                            $generateDate = $activeDate->copy()->subDays($generateDays);
                        } else {
                            $activeDate   = Carbon::create($today->year, $today->month, $pascabayarDay);
                            $generateDate = $activeDate->copy()->subDays($generateDays);
                        }

                        $isTime = $today->greaterThanOrEqualTo($generateDate);
                        return $isTime;
                    });

                $apiInstance = new InvoiceApi();

                foreach ($connections as $conn) {
                    $member = $conn->member;
                    $detail = $member->paymentDetail;
                    $price  = (int) $conn->profile->price;
                    $periode = 1;
                    $invoiceDate = $today;

                    if ($detail->payment_type === 'prabayar') {
                        $activeDate = Carbon::create(
                            $today->year,
                            $today->month,
                            Carbon::parse($detail->active_date)->day
                        )->addMonth();

                        $dueDate = $activeDate->copy();
                        $startPeriod = $activeDate->copy()->startOfMonth();
                        $endPeriod   = $activeDate->copy()->endOfMonth();
                    } else {
                        $activeDate   = Carbon::create($today->year, $today->month, $pascabayarDay);
                        $dueDate      = $activeDate->copy();
                        $startPeriod  = $today->copy()->startOfMonth();
                        $endPeriod    = $today->copy()->endOfMonth();
                    }

                    $futureInvoiceExists = InvoiceHomepass::where('connection_id', $conn->id)
                        ->get()
                        ->filter(function ($invoice) use ($startPeriod, $endPeriod) {
                            if (!$invoice->subscription_period) return false;
                            [$startStr, $endStr] = explode(' - ', $invoice->subscription_period);
                            try {
                                $start = Carbon::createFromFormat('d/m/Y', trim($startStr));
                                $end   = Carbon::createFromFormat('d/m/Y', trim($endStr));
                            } catch (\Exception $e) {
                                return false;
                            }
                            return $start->equalTo($startPeriod) && $end->equalTo($endPeriod);
                        })
                        ->isNotEmpty();

                    if ($futureInvoiceExists) {
                        Log::info("⚠️ Invoice sudah ada untuk {$member->full_name}, skip.");
                        continue;
                    }

                    $subtotal = $price * $periode;
                    $ppnAmount = !empty($detail->ppn) ? ($subtotal * $detail->ppn / 100) : 0;
                    $discountAmount = !empty($detail->discount) ? ($subtotal * $detail->discount / 100) : 0;
                    $total_amount = $subtotal + $ppnAmount - $discountAmount;

                    $invNumber = InvoiceHelper::generateInvoiceNumber($conn->area->id ?? 0, 'H');
                    $duration  = InvoiceHelper::invoiceDurationThisMonth();
                    $next_inv_date = $dueDate->copy()->addMonth();

                    $create_invoice_request = new CreateInvoiceRequest([
                        'external_id'      => $invNumber,
                        'description'      => 'Tagihan nomor internet ' . $conn->internet_number,
                        'amount'           => intval($total_amount),
                        'invoice_duration' => $duration,
                        'currency'         => 'IDR',
                        'payer_email'      => $member->email ?? 'customer@amanisp.net.id',
                        'reminder_time'    => 1,
                    ]);
                    $generateInvoice = $apiInstance->createInvoice($create_invoice_request);

                    $subscriptionPeriodStr = $startPeriod->format('d/m/Y') . ' - ' . $endPeriod->format('d/m/Y');

                    $invoice = InvoiceHomepass::create([
                        'connection_id'       => $conn->id,
                        'member_id'           => $member->id,
                        'invoice_type'        => 'H',
                        'start_date'          => $invoiceDate,
                        'due_date'            => $dueDate,
                        'subscription_period' => $subscriptionPeriodStr,
                        'inv_number'          => $invNumber,
                        'amount'              => $total_amount,
                        'status'              => 'unpaid',
                        'group_id'            => $conn->group_id,
                        'next_inv_date'       => $next_inv_date,
                        'payment_url'         => $generateInvoice['invoice_url'],
                    ]);

                    PaymentDetail::findOrFail($member->payment_detail_id)->update([
                        'next_invoice' => $next_inv_date,
                    ]);

                    $apiKey = $this->getApiKey($conn->group_id);
                    if ($apiKey) {
                        $footer = GlobalSettings::where('group_id', $conn->group_id)->value('footer');
                        $this->whatsappService->sendFromTemplate(
                            $apiKey,
                            $member->phone_number,
                            'invoice_terbit',
                            [
                                'full_name'   => $member->fullname,
                                'uid'         => $conn->internet_number,
                                'no_invoice'  => '#' . $invoice->inv_number,
                                'amount'      => 'Rp ' . number_format($subtotal, 0, ',', '.'),
                                'total'       => 'Rp ' . number_format($total_amount, 0, ',', '.'),
                                'ppn'         => $detail->ppn,
                                'discount'    => $detail->discount,
                                'pppoe_user'  => $conn->username,
                                'pppoe_profile' => $conn->profile->name,
                                'due_date'    => $dueDate->format('d/m/Y'),
                                'period'      => $subscriptionPeriodStr,
                                'footer'      => $footer,
                            ],
                            ['group_id' => $conn->group_id]
                        );
                    }

                    Log::info("✅ Invoice {$invoice->inv_number} dibuat untuk {$member->fullname}");
                }
            }

            Log::info('=== GenerateMonthlyInvoiceJob selesai ===');
        } catch (\Throwable $th) {
            Log::error("❌ Error GenerateMonthlyInvoiceJob: " . $th->getMessage());
        }
    }
}
