<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerPortal extends Controller
{

    public function checkIdentity(Request $request)
    {
        $request->validate([
            'internet_number' => 'required',
            'phone'           => 'required'
        ]);

        // Cari koneksi berdasarkan internet number dan nomor HP member
        $connection = Connection::where('internet_number', $request->internet_number)
            ->whereHas('member', function ($query) use ($request) {
                $query->where('phone_number', $request->phone);
            })->first();

        if (!$connection) {
            return ResponseFormatter::error(null, 'Kombinasi Internet Number dan Nomor HP tidak valid.', 404);
        }

        $member = $connection->member;

        // Cek apakah pelanggan sudah pernah membuat PIN atau belum
        if (is_null($member->pin)) {
            return ResponseFormatter::success([
                'member_id' => $member->id,
                'action'    => 'setup_pin'
            ], 'Silakan buat PIN baru.');
        }

        return ResponseFormatter::success([
            'member_id' => $member->id,
            'action'    => 'enter_pin'
        ], 'Silakan masukkan PIN Anda.');
    }

    /**
     * 2. Setup PIN untuk Pertama Kali
     */
    public function setupPin(Request $request)
    {
        $request->validate([
            'member_id' => 'required',
            'pin'       => 'required|digits:6'
        ]);

        $member = Member::find($request->member_id);

        if (!$member) {
            return ResponseFormatter::error(null, 'Member tidak ditemukan.', 404);
        }

        // Simpan PIN dengan enkripsi
        $member->pin = Hash::make($request->pin);
        $member->save();

        // Buat Token Sanctum
        $token = $member->createToken('android_app_token')->plainTextToken;

        return ResponseFormatter::success([
            'token'  => $token,
            'member' => $member
        ], 'PIN berhasil dibuat dan login sukses.');
    }

    /**
     * 3. Verifikasi PIN untuk Login Selanjutnya
     */
    public function verifyPin(Request $request)
    {
        $request->validate([
            'member_id' => 'required',
            'pin'       => 'required|digits:6'
        ]);

        $member = Member::find($request->member_id);

        // Cocokkan inputan PIN dengan PIN di database
        if (!$member || !Hash::check($request->pin, $member->pin)) {
            return ResponseFormatter::error(null, 'PIN yang Anda masukkan salah.', 401);
        }

        // Buat Token Sanctum baru
        $token = $member->createToken('android_app_token')->plainTextToken;

        return ResponseFormatter::success([
            'token'  => $token,
            'member' => $member
        ], 'Login berhasil.');
    }
}
