<?php

use App\Services\GenerateInvMitra;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\GlobalSettings;

// Schedule::call(function () {
//     $settings = GlobalSettings::first();

//     if (!$settings) {
//         return;
//     }

//     $invoice_generate_days = $settings->invoice_generate_days ?? 0;
//     $due_date_pascabayar = $settings->due_date_pascabayar ?? 1;

//     $dayOfMonth = max(1, min(31, $due_date_pascabayar - $invoice_generate_days));

//     // Run only on the calculated day
//     if (now()->day === $dayOfMonth) {
//         Artisan::call('app:generate-invoice-history --dummy');
//     }
// })->dailyAt('15:57');

// Schedule::command('app:check-scheduler-time')->everySecond();

// $invoice_generate_days = GlobalSettings::first()->invoice_generate_days ?? null;
// $due_date_pascabayar = GlobalSettings::first()->due_date_pascabayar ?? null;
// $dayOfMonth = max(1, min(31, $due_date_pascabayar - $invoice_generate_days));
// Schedule::command('app:generate-invoice-history --dummy')->monthlyOn($dayOfMonth, '12:22');

// cara 1: lebih spesifik (jam 00:00 setiap hari)
// Schedule::command('app:generate-monthly-invoice')->dailyAt('00:00');
