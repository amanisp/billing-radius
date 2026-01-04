<?php

use App\Models\Area;
use App\Models\Mitra;
use Illuminate\Support\Facades\DB;

if (!function_exists('generateCustomerNumber')) {
    function generateCustomerNumber($type, $areaId)
    {
        // Ambil kode area dari database berdasarkan area_id
        $areaCode = Area::where('id', $areaId)->first();
        DB::table('areas')->where('id', $areaId)->value('id');

        // Cek jumlah pelanggan dengan area_id yang sama

        // Ambil nomor urutan terakhir, jika ada

        // Buat nomor baru dengan format AMAN-P5001 atau AMAN-C5001

    }
}
