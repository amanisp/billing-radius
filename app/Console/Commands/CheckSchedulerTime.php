<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckSchedulerTime extends Command
{
    protected $signature = 'app:check-scheduler-time';

    protected $description = 'Outputs the current server time to test the scheduler';

    public function handle()
    {
        $now = Carbon::now();
        $this->info("Scheduler ran at: " . $now->toDateTimeString());
    }
}
