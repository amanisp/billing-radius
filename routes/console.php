<?php

use App\Services\GenerateInvMitra;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\GlobalSettings;


// 👇 TAMBAHKAN JADWAL BARU ANDA DI SINI 👇
Schedule::command('invoice:generate-bulk')
    ->dailyAt('00:00')
    ->timezone('Asia/Jakarta') // Sesuaikan zona waktu dengan server / lokasi pelanggan Anda
    ->withoutOverlapping();
