<?php

namespace App\Console\Commands;

use App\Helpers\InvoiceHelper;
use App\Models\Connection;
use App\Models\PaymentDetail;
use App\Models\Invoice;
use App\Models\InvoiceHomepass;
use App\Models\WhatsappMessageLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;

class GenerateInvoiceHistory extends Command
{
    protected $signature = 'app:generate-invoice-history
                            {--months=1 : How many months back to generate (default 1)}
                            {--connections=* : Optional list of connection IDs to limit the run}
                            {--dry-run : Do everything except persist to DB}
                            {--date= : The exact date (Y-m-d) for invoice generation}
                            {--dummy : Skip Xendit API and WA sending (test mode)}
                            {--payer= : payer id (default 3)}
                            {--current-month : Generate invoice for current month instead of history}
                            {--model=InvoiceHomepass : Which model to use (Invoice or InvoiceHomepass)}';

    protected $description = 'Generate invoice history (backfill) for connections based on members and payment_details';

    public function handle()
    {
        $months = (int) $this->option('months');
        $connectionFilter = $this->option('connections');
        $dryRun = (bool) $this->option('dry-run');
        $dateOption = $this->option('date');
        $isDummy = $this->option('dummy');
        $payerId = $this->option('payer') ?: 3;
        $isCurrentMonth = $this->option('current-month');
        $modelType = $this->option('model');

        // Validasi input
        if ($months <= 0) {
            $this->error('Invalid --months value. Must be >= 1');
            return 1;
        }

        // Validasi model type
        if (!in_array($modelType, ['Invoice', 'InvoiceHomepass'])) {
            $this->error('Invalid --model value. Must be "Invoice" or "InvoiceHomepass"');
            return 1;
        }

        $modelClass = $modelType === 'Invoice' ? Invoice::class : InvoiceHomepass::class;
        $payerId = is_numeric($payerId) ? (int)$payerId : 3;

        // Setup Xendit jika diperlukan
        if (!$isDummy && $isCurrentMonth && env('XENDIT_SECRET_KEY')) {
            Configuration::setXenditKey(env('XENDIT_SECRET_KEY'));
        }

        // Tentukan tanggal berdasarkan input atau hari ini
        if ($dateOption) {
            try {
                $today = Carbon::createFromFormat('Y-m-d', $dateOption)->startOfDay();
            } catch (\Exception $e) {
                $this->error("❌ Format tanggal salah. Gunakan YYYY-MM-DD");
                return 1;
            }
        } else {
            $today = Carbon::now()->startOfDay();
        }

        $mode = $isCurrentMonth ? 'current month' : 'history';
        $this->info("🚀 Starting invoice {$mode} generator — date={$today->format('Y-m-d')} months={$months} dry-run=" . ($dryRun ? 'yes' : 'no') . " dummy=" . ($isDummy ? 'yes' : 'no') . " model={$modelType}");

        // Set rentang tanggal
        if ($isCurrentMonth) {
            $startMonth = $today->copy()->startOfMonth();
            $endMonth = $today->copy()->endOfMonth();
        } else {
            $startMonth = $today->copy()->startOfMonth()->subMonths($months - 1);
            $endMonth = $today->copy()->startOfMonth();
        }

        $totalProcessed = 0;
        $totalCreated = 0;
        $totalSkipped = 0;
        $totalMissingPrice = 0;

        // Initialize Xendit API jika diperlukan
        $apiInstance = null;
        if (!$isDummy && $isCurrentMonth && env('XENDIT_SECRET_KEY')) {
            $apiInstance = new InvoiceApi();
        }

        // Build query untuk connections
        $query = Connection::with(['member', 'profile', 'group'])
        ->whereHas('member', function ($query){
            $query->where('billing', 1);
        });

        if (!empty($connectionFilter)) {
            $connectionIds = is_array($connectionFilter) ? $connectionFilter : explode(',', $connectionFilter);
            $connectionIds = array_filter(array_map('intval', $connectionIds));
            $this->info('Filtering connection IDs: ' . implode(',', $connectionIds));
            $query->whereIn('id', $connectionIds);
        }

        $connectionCount = $query->count();
        $this->info("Found {$connectionCount} connections to process");

        if ($connectionCount > 0) {
            // Process connections
            $query->chunkById(200, function ($connections) use (
                $startMonth, $endMonth, $dryRun, $isDummy, $isCurrentMonth, $payerId,
                $modelClass, $apiInstance, &$totalProcessed, &$totalCreated, &$totalSkipped, &$totalMissingPrice
            ) {
                foreach ($connections as $connection) {
                    $totalProcessed++;
                    $this->processConnection(
                        $connection, $startMonth, $endMonth, $dryRun, $isDummy,
                        $isCurrentMonth, $payerId, $modelClass, $apiInstance,
                        $totalCreated, $totalSkipped, $totalMissingPrice
                    );

                    // Rate limiting untuk current month generation
                    if ($isCurrentMonth && $totalProcessed % 15 === 0) {
                        $this->info("⏳ Jeda 5 detik untuk menghindari limit...");
                        sleep(5);
                    }
                }
            });
        } else {
            // Fallback: cari members dengan billing aktif atau payment_detail
            $this->info("No active connections found. Checking members with billing or payment_detail...");
            $this->processMemberFallback(
                $startMonth, $endMonth, $dryRun, $isDummy, $isCurrentMonth,
                $payerId, $modelClass, $apiInstance,
                $totalProcessed, $totalCreated, $totalSkipped, $totalMissingPrice
            );
        }

        $this->info("🎉 Done. processed={$totalProcessed} created={$totalCreated} skipped={$totalSkipped} missing_price={$totalMissingPrice}");
        return 0;
    }

    private function processConnection(
        $connection, $startMonth, $endMonth, $dryRun, $isDummy, $isCurrentMonth,
        $payerId, $modelClass, $apiInstance, &$totalCreated, &$totalSkipped, &$totalMissingPrice
    ) {
        $member = $connection->member;
        if (!$member) {
            $this->warn("Skipping connection#{$connection->id}: no member found");
            $totalMissingPrice++;
            return;
        }

        // Dapatkan harga dari payment_detail atau profile
        $price = $this->getConnectionPrice($connection, $member);
        if ($price <= 0) {
            $this->warn("Skipping connection#{$connection->id} ({$connection->username}): missing price");
            $totalMissingPrice++;
            return;
        }

        $pointer = $startMonth->copy();
        while ($pointer->lte($endMonth)) {
            $periodStart = $pointer->copy()->startOfMonth();
            $periodEnd = $pointer->copy()->endOfMonth();

            // Cek apakah invoice sudah ada
            $exists = $modelClass::where('connection_id', $connection->id) // Ubah dari pppoe_id ke connection_id
                ->whereYear('start_date', $periodStart->year)
                ->whereMonth('start_date', $periodStart->month)
                ->exists();

            if ($exists) {
                $totalSkipped++;
                $pointer->addMonth();
                continue;
            }

            $periode = 1;
            $total_amount = $price * $periode;
            $periodeText = $periodStart->format('d/m/Y') . ' - ' . $periodEnd->format('d/m/Y');
            $dueDate = $periodStart->copy()->day(25);
            $nextInvDate = $periodStart->copy()->addMonthNoOverflow()->startOfMonth()->day(10);

            // Generate invoice number
            $invNumber = $this->generateInvoiceNumber($connection, $periodStart, $isCurrentMonth, $modelClass);
            if (!$invNumber) {
                $this->error("❌ Failed to generate invoice number for connection {$connection->id}");
                $pointer->addMonth();
                continue;
            }

            // Handle Xendit invoice creation untuk current month
            $paymentUrl = '';
            if ($isCurrentMonth && !$isDummy && $apiInstance) {
                $paymentUrl = $this->createXenditInvoice(
                    $apiInstance, $invNumber, $connection, $member, $total_amount, $periodeText
                );
                if ($paymentUrl === false) {
                    $pointer->addMonth();
                    continue;
                }
            } elseif ($isCurrentMonth && $isDummy) {
                $paymentUrl = 'https://dummy-payment.local/invoice/' . $invNumber;
            }

            // Siapkan data untuk insert
            $payload = [
                'connection_id' => $connection->id, // Ubah dari pppoe_id ke connection_id
                'member_id' => $member->id,
                'payment_detail_id' => $member->payment_detail_id,
                'payer_id' => $member->payer_id ?? $payerId,
                'invoice_type' => 'H',
                'start_date' => $periodStart->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'subscription_period' => $periodeText,
                'inv_number' => $invNumber,
                'amount' => $total_amount,
                'status' => 'unpaid',
                'group_id' => $connection->group_id ?? $member->group_id,
                'next_inv_date' => $nextInvDate,
                'payment_url' => $paymentUrl,
            ];

            if ($dryRun) {
                $this->line("[DRY] Connection#{$connection->id} ({$connection->username}) period={$periodStart->format('Y-m')} -> inv={$invNumber} amount={$total_amount}");
                $totalCreated++;
            } else {
                if ($this->createInvoice($payload, $modelClass, $connection)) {
                    $totalCreated++;

                    // Update next_inv_date untuk current month
                    if ($isCurrentMonth) {
                        $connection->update(['next_inv_date' => $nextInvDate]);
                    }

                    // Send WhatsApp notification
                    if ($isCurrentMonth && !$isDummy && !empty($member->phone_number)) {
                        $this->sendWhatsAppNotification(
                            $connection, $member, $invNumber, $total_amount,
                            $price, $dueDate, $paymentUrl, $periodStart
                        );
                        sleep(1); // Rate limiting
                    }
                    Log::info("Invoice created: {$invNumber} for connection {$connection->id}");
                    $this->info("✅ Invoice untuk {$connection->username} berhasil dibuat: {$invNumber}");
                }
            }

            $pointer->addMonth();
        }
    }

    private function processMemberFallback(
        $startMonth, $endMonth, $dryRun, $isDummy, $isCurrentMonth,
        $payerId, $modelClass, $apiInstance,
        &$totalProcessed, &$totalCreated, &$totalSkipped, &$totalMissingPrice
    ) {
        $members = DB::table('members as m')
            ->leftJoin('connections as c', 'm.connection_id', '=', 'c.id')
            ->leftJoin('profiles as p', 'c.profile_id', '=', 'p.id')
            ->leftJoin('payment_details as pd', 'm.payment_detail_id', '=', 'pd.id')
            ->leftJoin('groups as g', 'm.group_id', '=', 'g.id')
            ->where(function ($q) {
                $q->where('m.billing', 1)
                  ->orWhereNotNull('m.payment_detail_id');
            })
            ->select(
                'm.id as member_id',
                'm.fullname as member_name',
                'm.email as member_email',
                'm.phone_number as member_phone',
                'm.payment_detail_id',
                'pd.amount as payment_amount',
                'c.id as connection_id',
                'c.username as connection_username',
                'c.internet_number',
                'p.price as profile_price',
                'p.name as profile_name',
                'm.group_id',
                'g.wa_api_token'
            )
            ->get();

        $this->info("Found {$members->count()} members to process from fallback");

        foreach ($members as $memberData) {
            $totalProcessed++;

            // Dapatkan harga
            $price = (int)($memberData->payment_amount ?? $memberData->profile_price ?? 0);
            if ($price <= 0) {
                $this->warn("Skipping member#{$memberData->member_id}: missing price");
                $totalMissingPrice++;
                continue;
            }

            $connectionId = $memberData->connection_id ?? 'm' . $memberData->member_id;
            $username = $memberData->connection_username ?? $memberData->member_name;

            $pointer = $startMonth->copy();
            while ($pointer->lte($endMonth)) {
                $periodStart = $pointer->copy()->startOfMonth();

                // Cek apakah invoice sudah ada
                $exists = $modelClass::where('connection_id', $connectionId)
                    ->whereYear('start_date', $periodStart->year)
                    ->whereMonth('start_date', $periodStart->month)
                    ->exists();

                if ($exists) {
                    $totalSkipped++;
                    $pointer->addMonth();
                    continue;
                }

                $total_amount = $price;
                $periodeText = $periodStart->format('d/m/Y') . ' - ' . $periodStart->copy()->endOfMonth()->format('d/m/Y');
                $dueDate = $periodStart->copy()->day(25);
                $nextInvDate = $periodStart->copy()->addMonthNoOverflow()->startOfMonth()->day(10);

                // Generate simple invoice number untuk fallback
                $invNumber = 'INV-H-' . $memberData->group_id . '-' . $periodStart->format('Ym') . '-' . $connectionId;

                // Pastikan unique
                $counter = 1;
                while ($modelClass::where('inv_number', $invNumber)->exists()) {
                    $invNumber = 'INV-H-' . $memberData->group_id . '-' . $periodStart->format('Ym') . '-' . $connectionId . '-' . $counter;
                    $counter++;
                    if ($counter > 100) break; // Prevent infinite loop
                }

                $payload = [
                    'connection_id' => $connectionId,
                    'member_id' => $memberData->member_id,
                    'payment_detail_id' => $memberData->payment_detail_id,
                    'payer_id' => $payerId,
                    'invoice_type' => 'H',
                    'start_date' => $periodStart->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'subscription_period' => $periodeText,
                    'inv_number' => $invNumber,
                    'amount' => $total_amount,
                    'status' => 'unpaid',
                    'group_id' => $memberData->group_id,
                    'next_inv_date' => $nextInvDate,
                    'payment_url' => '',
                ];

                if ($dryRun) {
                    $this->line("[DRY] Member#{$memberData->member_id} ({$username}) period={$periodStart->format('Y-m')} -> inv={$invNumber} amount={$total_amount}");
                    $totalCreated++;
                } else {
                    if ($this->createInvoice($payload, $modelClass)) {
                        $totalCreated++;
                        $this->info("✅ Invoice untuk member {$memberData->member_name} berhasil dibuat: {$invNumber}");
                    }
                }

                $pointer->addMonth();
            }
        }
    }

    private function getConnectionPrice($connection, $member)
    {
        // Priority: payment_detail amount -> profile price
        if ($member->payment_detail_id) {
            try {
                $paymentDetail = PaymentDetail::find($member->payment_detail_id);
                if ($paymentDetail && $paymentDetail->amount > 0) {
                    return (int)$paymentDetail->amount;
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to get payment detail for member {$member->id}: " . $e->getMessage());
            }
        }

        if ($connection->profile && $connection->profile->price > 0) {
            return (int)$connection->profile->price;
        }

        return 0;
    }

    private function generateInvoiceNumber($connection, $periodStart, $isCurrentMonth, $modelClass)
    {
        try {
            $areaId = $connection->group_id ?? 1;

            if ($isCurrentMonth) {
                return InvoiceHelper::generateInvoiceNumber($areaId, 'H', $modelClass);
            } else {
                return InvoiceHelper::generateBulkInvoiceNumber($areaId, 'H', $periodStart, $connection->id, $modelClass);
            }
        } catch (\Throwable $e) {
            Log::error("InvoiceHelper failed for connection {$connection->id}: " . $e->getMessage());

            // Fallback invoice number
            $hash = substr(md5(uniqid((string)$connection->id, true)), 0, 6);
            return "TMP-H-{$connection->id}-" . $periodStart->format('Ym') . "-{$hash}";
        }
    }

    private function createXenditInvoice($apiInstance, $invNumber, $connection, $member, $total_amount, $periodeText)
    {
        try {
            $create_invoice_request = new CreateInvoiceRequest([
                'external_id' => $invNumber,
                'description' => 'Tagihan internet ' . ($connection->internet_number ?? $connection->username) . ' Periode: ' . $periodeText,
                'amount' => $total_amount,
                'invoice_duration' => 1728000, // 20 days
                'currency' => 'IDR',
                'payer_email' => $member->email ?? 'customer@amanisp.net.id',
                'reminder_time' => 1
            ]);

            $response = $apiInstance->createInvoice($create_invoice_request);

            if (is_array($response) && isset($response['invoice_url'])) {
                return $response['invoice_url'];
            } elseif (is_object($response) && isset($response->invoice_url)) {
                return $response->invoice_url;
            }

            return '';
        } catch (\Throwable $e) {
            Log::error("Xendit error for connection {$connection->id}: " . $e->getMessage());
            $this->error("❌ Xendit error for {$connection->username}: " . $e->getMessage());
            return false;
        }
    }

    private function createInvoice($payload, $modelClass, $connection = null)
    {
        try {
            DB::transaction(function () use ($payload, $modelClass) {
                // Final race-safety check
                $exists = $modelClass::where('connection_id', $payload['connection_id'])
                    ->where('start_date', $payload['start_date'])
                    ->exists();

                if (!$exists) {
                    $modelClass::create($payload);
                }
            });
            return true;
        } catch (\Throwable $e) {
            Log::error("Failed creating invoice: " . $e->getMessage());
            $this->error("❌ Error creating invoice: " . $e->getMessage());
            return false;
        }
    }

    private function sendWhatsAppNotification($connection, $member, $invNumber, $total_amount, $price, $dueDate, $paymentUrl, $periodStart)
    {
        try {
            $memberName = $member->fullname ?? $member->email ?? 'Pelanggan';
            $profileName = $connection->profile->name ?? '';

            $message = "Salam Bpk/Ibu {$memberName}\n\n" .
                "*Invoice Anda Telah Terbit*, berikut rinciannya:\n" .
                "ID Pelanggan: " . ($connection->internet_number ?? $connection->username) . "\n" .
                "Nomor Invoice: {$invNumber}\n" .
                "Amount: Rp " . number_format($price, 0, ',', '.') . "\n" .
                "Total: Rp " . number_format($total_amount, 0, ',', '.') . "\n" .
                "Item: Internet {$connection->username} - Paket {$profileName}\n" .
                "Jatuh tempo: {$dueDate->format('d/m/Y')}\n" .
                "Period: {$periodStart->format('d/m/Y')} - " . $periodStart->copy()->addMonth()->format('d/m/Y') . "\n\n" .
                "💳 *Metode Pembayaran Otomatis:*\n" .
                "Bank Virtual Account, OVO, DANA, LinkAja, ShopeePay, Alfamart, QRIS\n" .
                "Klik => {$paymentUrl}\n\n" .
                "Terima kasih.\n" .
                "_Ini adalah pesan otomatis - mohon untuk tidak membalas langsung ke pesan ini_";

            $response = Http::withHeaders([
                'x-api-key' => env('WA_API_TOKEN'),
            ])->post('https://wa.amanisp.net.id/api/send-message', [
                'sessionId' => $connection->group->wa_api_token,
                'number' => $member->phone_number,
                'message' => $message,
                'group_id' => $connection->group_id,
                'subject' => 'Invoice'
            ]);

            WhatsappMessageLog::create([
                'group_id' => $connection->group_id,
                'phone' => $member->phone_number,
                'subject' => 'Invoice Created ' . $invNumber,
                'message' => $message,
                'session_id' => $connection->group->wa_api_token,
                'status' => $response->successful() ? 'sent' : 'failed',
                'sent_at' => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error("Failed sending WA notification for connection {$connection->id}: " . $e->getMessage());
            $this->warn("⚠️ WA notification failed for {$connection->username}");
        }
    }
}
