<?php

namespace App\Imports;

use App\Jobs\ImportPppoeAccountRow;
use App\Models\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\AfterImport;

/**
 * Kelas utama import Excel — hanya menjalankan sheet pertama.
 */
class PppoeAccountsImport implements WithMultipleSheets
{
    protected $group_id;
    protected $firstSheetInstance;

    public function __construct($group_id)
    {
        $this->group_id = $group_id;
        $this->firstSheetInstance = new PppoeAccountsFirstSheetImport($group_id);
    }

    public function sheets(): array
    {
        // Jalankan hanya sheet pertama (index 0)
        return [
            0 => $this->firstSheetInstance,
        ];
    }

    /**
     * Ambil import batch ID dari sheet pertama agar bisa diakses dari controller.
     */
    public function getImportBatchId(): string
    {
        return $this->firstSheetInstance->getImportBatchId();
    }
}

/**
 * Implementasi import untuk sheet pertama.
 */
class PppoeAccountsFirstSheetImport implements ToCollection, WithChunkReading, WithStartRow, WithEvents
{
    protected $group_id;
    protected $importBatchId;
    protected $currentRow = 6;
    protected $totalProcessed = 0;
    protected $totalSkipped = 0;

    public function __construct($group_id)
    {
        $this->group_id = $group_id;
        $this->importBatchId = Str::uuid()->toString();
    }

    public function startRow(): int
    {
        return 7; // mulai dari baris ke-7 (skip header)
    }

    public function chunkSize(): int
    {
        return 500; // proses per 500 row
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $this->currentRow++;

            // skip row kosong
            if ($this->isEmptyRow($row)) {
                $this->totalSkipped++;
                continue;
            }

            // jika kolom 0 adalah PPPoE
            if (isset($row[0]) && strcasecmp(trim($row[0]), 'PPPoE') === 0) {
                if (!isset($row[2]) || trim($row[2]) === '') {
                    $this->totalSkipped++;
                    Log::warning('Skipping row due to empty username (type PPPoE)', [
                        'row_number' => $this->currentRow,
                        'batch_id' => $this->importBatchId,
                    ]);
                    continue;
                }

                // pindahkan kolom username ke index 0
                $row[0] = trim($row[2]);
            } else {
                // jika bukan PPPoE, maka pastikan ada MAC address
                if (!isset($row[1]) || trim($row[1]) === '') {
                    $this->totalSkipped++;
                    Log::warning('Skipping row due to empty MAC address', [
                        'row_number' => $this->currentRow,
                        'batch_id' => $this->importBatchId,
                    ]);
                    continue;
                }
            }

            // jalankan job
            ImportPppoeAccountRow::dispatch(
                $row->toArray(),
                $this->group_id,
                $this->currentRow,
                $this->importBatchId,
                Auth::user()->name ?? 'system',
                Auth::user()->role ?? 'system'
            )->onQueue('imports');

            $this->totalProcessed++;
        }
    }

    protected function isEmptyRow($row): bool
    {
        if ($row instanceof Collection) {
            $row = $row->toArray();
        }

        foreach ($row as $cell) {
            if (!empty(trim((string) $cell))) {
                return false;
            }
        }
        return true;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function () {
                Log::info('Import started', [
                    'batch_id' => $this->importBatchId,
                    'group_id' => $this->group_id,
                    'started_at' => now(),
                ]);
                $this->createImportBatchRecord();
            },

            AfterImport::class => function () {
                Log::info('Import completed', [
                    'batch_id' => $this->importBatchId,
                    'group_id' => $this->group_id,
                    'total_processed' => $this->totalProcessed,
                    'total_skipped' => $this->totalSkipped,
                    'completed_at' => now(),
                ]);
                $this->updateImportBatchRecord();
            },
        ];
    }

    protected function createImportBatchRecord()
    {
        try {
            Batch::create([
                'id' => $this->importBatchId,
                'group_id' => $this->group_id,
                'type' => 'pppoe_accounts',
                'status' => 'processing',
                'total_rows' => 0,
                'processed_rows' => 0,
                'failed_rows' => 0,
                'started_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create import batch record', [
                'error' => $e->getMessage(),
                'batch_id' => $this->importBatchId,
            ]);
        }
    }

    protected function updateImportBatchRecord()
    {
        try {
            $failedCount = DB::table('import_error_logs')
                ->where('import_batch_id', $this->importBatchId)
                ->count();

            DB::table('import_batches')
                ->where('id', $this->importBatchId)
                ->update([
                    'status' => $failedCount > 0 ? 'completed_with_errors' : 'completed',
                    'total_rows' => $this->totalProcessed + $this->totalSkipped,
                    'processed_rows' => $this->totalProcessed,
                    'failed_rows' => $failedCount,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::info("updateImportBatchRecord called", [
                'batch_id' => $this->importBatchId,
                'total_rows' => $this->totalProcessed + $this->totalSkipped,
                'processed_rows' => $this->totalProcessed,
                'failed_rows' => $failedCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update import batch record', [
                'error' => $e->getMessage(),
                'batch_id' => $this->importBatchId,
            ]);
        }
    }

    public function getImportBatchId(): string
    {
        return $this->importBatchId;
    }
}
