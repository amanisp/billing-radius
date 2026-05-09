<?php

namespace App\Helpers;

use App\Models\Invoice;
use App\Models\InvoiceHomepass;
use App\Models\Area;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceHelper
{
    /**
     * Generate nomor invoice dengan format INVXX-XXXXXXX
     * Format: INV[Segmentasi][Area]-[Tahun][Bulan][NomorUrut]
     *
     * @param int $areaId ID area dari database
     * @param string $type Tipe invoice (H untuk Homepass, dll)
     * @param string $modelClass Optional: specify which model to use
     * @param \Carbon\Carbon|string|null $periodStart Optional: invoice period used for year/month sequence
     * @return string
     */
    public static function generateInvoiceNumber($areaId, $type, $modelClass = null, $periodStart = null)
    {
        if (!$modelClass) {
            $modelClass = Invoice::class;
        }

        if (!$areaId) {
            throw new \Exception('Area invoice tidak valid.');
        }

        $segmentasi = $type;
        $kodeArea = $areaId;
        $period = $periodStart instanceof Carbon
            ? $periodStart->copy()
            : ($periodStart ? Carbon::parse($periodStart) : now());

        $year = $period->format('y');
        $month = $period->format('m');

        return DB::transaction(function () use ($modelClass, $type, $areaId, $segmentasi, $kodeArea, $year, $month, $period) {
            $area = Area::whereKey($areaId)->lockForUpdate()->first();

            if (!$area) {
                throw new \Exception("Area invoice tidak ditemukan: {$areaId}");
            }

            // Use lockForUpdate untuk row-level locking
            $latestInvoice = $modelClass::where('invoice_type', $type)
                ->whereYear('start_date', $period->year)
                ->whereMonth('start_date', $period->month)
                ->where('inv_number', 'like', "INV{$segmentasi}{$kodeArea}-{$year}{$month}%")
                ->lockForUpdate()
                ->orderByDesc('inv_number')
                ->first();

            $nextNumber = 1;
            if ($latestInvoice) {
                $pattern = "/INV{$segmentasi}{$kodeArea}-{$year}{$month}(\d{3})/";
                if (preg_match($pattern, $latestInvoice->inv_number, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                }
            }

            // Format nomor urut ke 3 digit
            $numberPart = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            $invoiceNumber = sprintf('INV%s%s-%s%s%s', $segmentasi, $kodeArea, $year, $month, $numberPart);

            // Double check uniqueness
            while ($modelClass::where('inv_number', $invoiceNumber)->exists()) {
                $nextNumber++;
                $numberPart = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                $invoiceNumber = sprintf('INV%s%s-%s%s%s', $segmentasi, $kodeArea, $year, $month, $numberPart);
            }

            return $invoiceNumber;
        });
    }

    /**
     * Generate invoice number khusus untuk bulk generation (history mode)
     */
    public static function generateBulkInvoiceNumber($areaId, $type, $periodStart, $pppoeId, $modelClass)
    {
        $segmentasi = $type;
        $kodeArea = $areaId;
        $year = $periodStart->format('y');
        $month = $periodStart->format('m');

        return DB::transaction(function () use ($modelClass, $type, $areaId, $segmentasi, $kodeArea, $year, $month, $periodStart, $pppoeId) {

            // Use lockForUpdate untuk consistency
            $baseNumber = $modelClass::where('invoice_type', $type)
                ->whereYear('start_date', $periodStart->year)
                ->whereMonth('start_date', $periodStart->month)
                ->where('inv_number', 'like', "INV{$type}{$areaId}-{$year}{$month}%")
                ->lockForUpdate()
                ->count() + 1;

            $numberPart = str_pad($baseNumber, 3, '0', STR_PAD_LEFT);
            $invoiceNumber = sprintf('INV%s%s-%s%s%s', $segmentasi, $kodeArea, $year, $month, $numberPart);

            // Jika collision, tambahkan suffix unik
            if ($modelClass::where('inv_number', $invoiceNumber)->exists()) {
                $pppoeIdPadded = str_pad($pppoeId, 4, '0', STR_PAD_LEFT);
                $periodSuffix = $periodStart->format('Ym');
                $invoiceNumber = sprintf(
                    'INV%s%s-%s%s%s-%s-%s',
                    $segmentasi,
                    $kodeArea,
                    $year,
                    $month,
                    $numberPart,
                    $periodSuffix,
                    $pppoeIdPadded
                );
            }

            return $invoiceNumber;
        });
    }


    // Helper duedate xendit
    public static function invoiceDurationThisMonth(string $date = null): int
    {
        // default hari ini
        $date = $date ? Carbon::parse($date) : now();

        // akhir bulan
        $endOfMonth = $date->copy()->endOfMonth();

        // selisih hari
        $daysLeft = $date->diffInDays($endOfMonth);

        // convert ke detik
        return $daysLeft * 86400;
    }
}
