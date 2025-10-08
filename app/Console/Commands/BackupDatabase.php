<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Tasks\Backup\BackupJob;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = 'backup-' . Carbon::now()->format('Y-m-d_H-i-s') . '.sql';
        $storagePath = storage_path("backup/{$filename}");

        $dbHost = env('DB_HOST');
        $dbPort = env('DB_PORT');
        $dbName = env('DB_DATABASE');
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD');

        // Jalankan mysqldump menggunakan exec()
        $command = "mysqldump --user={$dbUser} --password={$dbPass} --host={$dbHost} --port={$dbPort} {$dbName} > \"$storagePath\"";
        // dd($command);
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            Log::error("Backup database gagal. Kode error: " . $return_var . "\nOutput: " . implode("\n", $output));
            $this->error("Backup gagal. Kode error: " . $return_var);
            return; // Batalkan proses
        }

        if (file_exists($storagePath)) {
            // Upload ke Google Drive
            Storage::disk('google')->put($filename, file_get_contents($storagePath));

            // Hapus file lokal setelah diunggah
            unlink($storagePath);

            Log::info("Backup database berhasil: {$filename}");
            $this->info("Backup berhasil: {$filename}");
        } else {
            Log::error("Backup database gagal. File backup tidak ditemukan.");
            $this->error("Backup gagal. File backup tidak ditemukan.");
        }
    }
}
