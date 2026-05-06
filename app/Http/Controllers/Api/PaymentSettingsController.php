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
            $settings = GlobalSettings::firstOrCreate(
                ['id' => 1],
                [
                    'xendit_balance' => 0.00,
                    'isolir_time' => '00:00:00',
                    'invoice_generate_days' => 7,
                    'notification_days' => 3,
                    'isolir_after_exp' => 1,
                    'due_date_pascabayar' => 20,
                ]
            );

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
        try {
            $user = $this->getAuthUser();
            if (!$user) return ResponseFormatter::error(null, 'Unauthorized', 401);

            $settings = GlobalSettings::first();
            if (!$settings) {
                $settings = new GlobalSettings();
                $settings->id = 1;
            }

            $oldData = $settings->toArray();

            $validated = $request->validate([
                'isolir_time'           => 'required',
                'invoice_generate_days' => 'required|integer|min:1',
                'notification_days'     => 'required|integer|min:0',
                'isolir_after_exp'      => 'required|integer|min:0',
                'due_date_pascabayar'   => 'nullable|integer|between:1,31',
                'footer'                => 'nullable|string',
            ]);

            $settings->fill($validated);
            $settings->save();

            // Mencatat perubahan ke Activity Log
            ActivityLogController::logUpdate($oldData, 'global_settings', $settings->fresh());

            return ResponseFormatter::success($settings, 'Pengaturan berhasil diperbarui');
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF([
                'action' => 'update_payment_settings',
                'error' => $th->getMessage()
            ], 'global_settings');

            return ResponseFormatter::error(null, $th->getMessage(), 200);
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
