<?php

namespace App\Console;

use App\Jobs\GenerateMonthlyInvoiceJob;
use App\Services\WhatsappService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log as FacadesLog;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new GenerateMonthlyInvoiceJob(app(WhatsappService::class)))
            ->dailyAt('07:00') // setiap malam jam 23:55
            ->withoutOverlapping()
            ->onOneServer(); // aman untuk multi-server
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
