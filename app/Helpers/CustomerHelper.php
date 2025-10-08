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
        $lastCustomer = Mitra::where('area_id', $areaId)->where('segmentasi', $type)->orderBy('id', 'desc')->first();

        // Ambil nomor urutan terakhir, jika ada
        $lastNumber = $lastCustomer ? intval(substr($lastCustomer->customer_number, -3)) : 0;

        // Buat nomor baru dengan format AMAN-P5001 atau AMAN-C5001
        $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

        return "AMAN-{$type}{$areaCode->area_code}{$newNumber}";
    }
}
