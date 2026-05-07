<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
use App\Models\GlobalSettings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentSettingsController extends Controller
{
    /**
     * Mendapatkan user yang terautentikasi dengan proteksi instance.
     */
    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }

    /**
     * Mengambil data pengaturan pembayaran.
     * Menggunakan model GlobalSettings (Single Row).
     */
    public function index()
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return ResponseFormatter::error(null, 'Unauthorized', 401);

            // Mengambil data pertama atau inisialisasi jika belum ada
            $settings = GlobalSettings::where('group_id', $user->group_id)->first();

            return ResponseFormatter::success($settings, 'Pengaturan pembayaran berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * Memperbarui pengaturan pembayaran.
     */
    public function update(Request $request)
    {
        // 1. PINDAHKAN VALIDASI KE SINI (Luar Try-Catch)
        // Jika validasi gagal, Laravel otomatis mereturn 422 (Bad Request/Unprocessable)
        $validated = $request->validate([
            'isolir_time'           => 'required',
            'invoice_generate_days' => 'required|integer|min:1',
            'isolir_after_exp'      => 'required|integer|min:0',
            'due_date_pascabayar'   => 'required|integer|between:1,31',
            'footer'                => 'nullable|string',
        ]);

        try {
            $user = $this->getAuthUser();
            if (!$user) return ResponseFormatter::error(null, 'Unauthorized', 401);

            $settings = GlobalSettings::where('group_id', $user->group_id)->first();

            if (!$settings) {
                return ResponseFormatter::error(null, 'Pengaturan tidak ditemukan untuk group ini', 404);
            }

            $oldData = $settings->toArray();

            $settings->fill($validated);
            $settings->save();

            // Mencatat perubahan ke Activity Log
            ActivityLogController::logUpdate($oldData, 'global_settings', $settings->fresh());

            return ResponseFormatter::success($settings, 'Pengaturan berhasil diperbarui');
        } catch (\Throwable $th) {
            // Jika masuk ke sini, berarti ada error database atau sistem
            ActivityLogController::logCreateF([
                'action' => 'update_payment_settings',
                'error' => $th->getMessage()
            ], 'global_settings');

            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * Sinkronisasi Saldo Xendit.
     * Fungsi ini biasanya dipanggil melalui internal service atau webhook.
     */
    public function updateBalance(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return ResponseFormatter::error(null, 'Unauthorized', 401);

            $settings = GlobalSettings::first();

            $validated = $request->validate([
                'amount' => 'required|numeric'
            ]);

            $settings->update([
                'xendit_balance' => $validated['amount']
            ]);

            return ResponseFormatter::success($settings, 'Saldo Xendit berhasil diperbarui');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
