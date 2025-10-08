<?php

namespace App\Console\Commands;

use App\Helpers\InvoiceHelper;
use App\Models\Connection;
use App\Models\GlobalSettings;
use App\Models\Groups;
use App\Models\invoiceHomepass;
use App\Models\PaymentDetail;
use App\Models\pppoeAccount;
use App\Models\WhatsappMessageLog;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;
use Xendit\Configuration;

class GenerateMonthlyInvoice extends Command
{
    protected $signature = 'app:generate-monthly-invoice';

    protected $description = 'Auto Generate Invoice';
    protected $whatsappService;

    public function __construct(WhatsappService $whatsappService)
    {
        parent::__construct();
        $this->whatsappService = $whatsappService;
        Configuration::setXenditKey(env('XENDIT_SECRET_KEY'));
    }

    private function getApiKey($groupId)
    {
        $settings = GlobalSettings::where('group_id', $groupId)->first();
        return $settings->whatsapp_api_key ?? null;
    }


    public function handle()
    {
        $this->info('🚀 Memulai generate invoice otomatis...');
        try {
            // ambil semua setting per mitra
            $settings = GlobalSettings::all();
            $today = Carbon::today();

            foreach ($settings as $setting) {
                $groupId       = $setting->group_id;
                $generateDays  = $setting->invoice_generate_days ?? 7; // default 7 hari sebelum due date
                $pascabayarDay = $setting->due_date_pascabayar ?? 10; // default tanggal 10

                // ambil semua koneksi aktif billing
                $connections = Connection::with(['member.PaymentDetail', 'profile'])
                    ->where('group_id', $groupId)
                    ->whereHas('member', function ($q) {
                        $q->where('billing', 1);
                    })
                    ->get()
                    ->filter(function ($conn) use ($today, $generateDays, $pascabayarDay) {
                        $detail = $conn->member->paymentDetail;
                        if (!$detail) {
                            Log::info("❌ {$conn->member->full_name} tidak punya PaymentDetail, skip");
                            return false;
                        }

                        if ($detail->payment_type === 'prabayar') {
                            // Ambil tanggal dari active_date asli (misal 07), lalu tanam di bulan sekarang
                            $activeDate = Carbon::create(
                                $today->year,
                                $today->month,
                                Carbon::parse($detail->active_date)->day
                            );

                            $generateDate = $activeDate->copy()->subDays($generateDays);

                            Log::info("🔄 [Prabayar] {$conn->member->full_name} 
                | active_date_asli=" . Carbon::parse($detail->active_date)->format('d/m/Y') . " 
                | activeDate={$activeDate->format('d/m/Y')} 
                | generateDate={$generateDate->format('d/m/Y')} 
                | today={$today->format('d/m/Y')}");
                        } else {
                            // Pascabayar → due date fix sesuai setting
                            $activeDate   = Carbon::create($today->year, $today->month, $pascabayarDay);
                            $generateDate = $activeDate->copy()->subDays($generateDays);

                            Log::info("💳 [Pascabayar] {$conn->member->full_name} 
                | activeDate={$activeDate->format('d/m/Y')} 
                | generateDate={$generateDate->format('d/m/Y')} 
                | today={$today->format('d/m/Y')}");
                        }

                        $isTime = $today->greaterThanOrEqualTo($generateDate);
                        Log::info("➡️ Result untuk {$conn->member->full_name} = " . ($isTime ? '✅ Generate' : '⏳ Belum waktunya'));

                        return $isTime;
                    });


                $apiInstance = new InvoiceApi();

                foreach ($connections as $conn) {
                    $member = $conn->member;
                    $detail = $member->paymentDetail;
                    $price  = (int) $conn->profile->price;

                    $invoiceDate = $today;
                    $periode     = 1;

                    // 🔹 Tentukan activeDate & dueDate
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
                        $activeDate = Carbon::create($today->year, $today->month, $pascabayarDay);
                        $dueDate    = $activeDate->copy();
                        $startPeriod = $today->copy()->startOfMonth();
                        $endPeriod   = $today->copy()->endOfMonth();
                    }

                    // 🔹 Cek invoice existing
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
                        $this->warn("⚠️ Invoice sudah ada untuk periode {$startPeriod->format('F Y')}. Skip.");
                        continue;
                    }

                    // 🔹 Hitung harga, ppn, diskon
                    $subtotal = $price * $periode;
                    $ppnAmount = !empty($detail->ppn) ? ($subtotal * $detail->ppn / 100) : 0;
                    $discountAmount = !empty($detail->discount) ? ($subtotal * $detail->discount / 100) : 0;
                    $total_amount = $subtotal + $ppnAmount - $discountAmount;

                    // nomor invoice
                    $invNumber = InvoiceHelper::generateInvoiceNumber($conn->group_id ?? 1, 'H');
                    $duration  = InvoiceHelper::invoiceDurationThisMonth();
                    $next_inv_date = $dueDate->copy()->addMonth();

                    // API invoice eksternal
                    $create_invoice_request = new CreateInvoiceRequest([
                        'external_id'      => $invNumber,
                        'description'      => 'Tagihan nomor internet ' . $conn->internet_number . ' Periode: 1 Bulan',
                        'amount'           => intval($total_amount),
                        'invoice_duration' => $duration,
                        'currency'         => 'IDR',
                        'payer_email'      => $member->email ?? 'customer@amanisp.net.id',
                        'reminder_time'    => 1,
                    ]);
                    $generateInvoice = $apiInstance->createInvoice($create_invoice_request);

                    // format periode
                    $subscriptionPeriodStr = $startPeriod->format('d/m/Y') . ' - ' . $endPeriod->format('d/m/Y');

                    // simpan ke DB
                    $invoiceData = [
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
                    ];
                    PaymentDetail::findOrFail($member->payment_detail_id)->update([
                        'next_invoice' => $next_inv_date,
                    ]);
                    $invoice = InvoiceHomepass::create($invoiceData);

                    // 🔹 WA notifikasi
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

                    $this->info("✅ Invoice {$invoice->inv_number} dibuat untuk {$member->fullname} periode {$subscriptionPeriodStr}");
                    $this->newLine();
                }
            }

            $this->info('=== Proses generate invoice selesai ===');
        } catch (\Throwable $th) {
            $this->error("❌ Error: " . $th->getMessage());
        }

        return Command::SUCCESS;
    }
}
