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

class PppoeAccountsImport implements WithMultipleSheets
{
    protected $group_id;

    public function __construct($group_id)
    {
        $this->group_id = $group_id;
    }

    /**
     * Ambil hanya sheet pertama
     */
    public function sheets(): array
    {
        // Sheet index dimulai dari 0, jadi [0] adalah sheet pertama
        return [
            0 => new PppoeAccountsFirstSheetImport($this->group_id)
        ];
    }
}

/**
 * Kelas khusus untuk menangani sheet pertama
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
        return 7;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $this->currentRow++;

            if ($this->isEmptyRow($row)) {
                $this->totalSkipped++;
                continue;
            }

            if (isset($row[0]) && strcasecmp(trim($row[0]), 'PPPoE') === 0) {
                if (!isset($row[2]) || trim($row[2]) === '') {
                    $this->totalSkipped++;
                    Log::warning('Skipping row due to empty username (type PPPoE)', [
                        'row_number' => $this->currentRow,
                        'batch_id' => $this->importBatchId
                    ]);
                    continue;
                }
                $row[0] = trim($row[2]);
            } else {
                if (!isset($row[1]) || trim($row[1]) === '') {
                    $this->totalSkipped++;
                    Log::warning('Skipping row due to empty MAC address', [
                        'row_number' => $this->currentRow,
                        'batch_id' => $this->importBatchId
                    ]);
                    continue;
                }
            }

            ImportPppoeAccountRow::dispatch(
                $row->toArray(),
                $this->group_id,
                $this->currentRow,
                $this->importBatchId,
                Auth::user()->name,
                Auth::user()->role
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
            if (!empty(trim((string)$cell))) {
                return false;
            }
        }
        return true;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                Log::info('Import started', [
                    'batch_id' => $this->importBatchId,
                    'group_id' => $this->group_id,
                    'started_at' => now()
                ]);
                $this->createImportBatchRecord();
            },

            AfterImport::class => function (AfterImport $event) {
                Log::info('Import completed', [
                    'batch_id' => $this->importBatchId,
                    'group_id' => $this->group_id,
                    'total_processed' => $this->totalProcessed,
                    'total_skipped' => $this->totalSkipped,
                    'completed_at' => now()
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
                'batch_id' => $this->importBatchId
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
                    'updated_at' => now()
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
                'batch_id' => $this->importBatchId
            ]);
        }
    }

    public function getImportBatchId(): string
    {
        return $this->importBatchId;
    }
}
