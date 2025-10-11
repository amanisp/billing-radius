<?php

namespace App\Imports;

use App\Jobs\ImportPppoeAccountRow;
use App\Models\ImportBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\AfterImport;

class PppoeAccountsImport implements ToCollection, WithChunkReading, WithStartRow, WithEvents
{
    protected $group_id;
    protected $importBatchId;
    protected $currentRow = 6; // Starting from row 7 (0-indexed + 1)
    protected $totalProcessed = 0;
    protected $totalSkipped = 0;

    public function __construct($group_id)
    {
        $this->group_id = $group_id;
        $this->importBatchId = Str::uuid()->toString();
    }

    public function startRow(): int
    {
        return 7; // Skip header rows
    }

    public function chunkSize(): int
    {
        return 500; // Process 500 rows per chunk
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $this->currentRow++;

            // Skip completely empty rows
            if ($this->isEmptyRow($row)) {
                $this->totalSkipped++;
                continue;
            }

            // Skip if username is empty
            if (!isset($row[0]) || trim($row[0]) === '') {
                $this->totalSkipped++;
                Log::warning('Skipping row due to empty username', [
                    'row_number' => $this->currentRow,
                    'batch_id' => $this->importBatchId
                ]);
                continue;
            }

            // Dispatch job dengan row number dan batch ID
            ImportPppoeAccountRow::dispatch(
                $row->toArray(),
                $this->group_id,
                $this->currentRow,
                $this->importBatchId,
                Auth::user()->name,
                Auth::user()->role
            )->onQueue('imports'); // Use dedicated queue for imports

            $this->totalProcessed++;
        }
    }

    /**
     * Check if row is completely empty
     */
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

    /**
     * Register events
     */
    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                // Log import start
                Log::info('Import started', [
                    'batch_id' => $this->importBatchId,
                    'group_id' => $this->group_id,
                    'started_at' => now()
                ]);

                // Optionally create import batch record
                $this->createImportBatchRecord();
            },

            AfterImport::class => function (AfterImport $event) {
                // Log import completion
                Log::info('Import completed', [
                    'batch_id' => $this->importBatchId,
                    'group_id' => $this->group_id,
                    'total_processed' => $this->totalProcessed,
                    'total_skipped' => $this->totalSkipped,
                    'completed_at' => now()
                ]);

                // Update import batch record
                $this->updateImportBatchRecord();
            },
        ];
    }

    /**
     * Create import batch record for tracking
     */
    protected function createImportBatchRecord()
    {
        try {
            DB::table('import_batches')->insert([
                'id' => $this->importBatchId,
                'group_id' => $this->group_id,
                'type' => 'pppoe_accounts',
                'status' => 'processing',
                'total_rows' => 0,
                'processed_rows' => 0,
                'failed_rows' => 0,
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create import batch record', [
                'error' => $e->getMessage(),
                'batch_id' => $this->importBatchId
            ]);
        }
    }

    /**
     * Update import batch record after completion
     */
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

            //Debug log
            Log::info("updateImportBatchRecord called", [
                'batch_id' => $this->importBatchId,
                'total_rows' => $this->totalProcessed + $this->totalSkipped,
                'processed_rows' => $this->totalProcessed,
                'failed_rows' => $failedCount,
            ]);
            //end debug log
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
