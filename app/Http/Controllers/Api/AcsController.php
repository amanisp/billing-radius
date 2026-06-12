<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\GlobalSettings;
use App\Services\GenieAcsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AcsController extends Controller
{
    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }

    /**
     * Helper untuk mengecek setting dan menerapkan config mandiri jika diperlukan
     */
    private function applyAcsConfig(GenieAcsService $genie, $groupId)
    {
        $setting = GlobalSettings::where('group_id', $groupId)->first() ?? GlobalSettings::first();

        if ($setting && $setting->acs_mode === 'mandiri') {
            $genie->setCustomConfig(
                $setting->acs_url,
                $setting->acs_port,
                $setting->acs_username,
                $setting->acs_password
            );
        }

        return $genie;
    }

    // ==========================================
    // BAGIAN PENGATURAN MODE & CONFIG (BARU)
    // ==========================================

    public function getSettings(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $setting = GlobalSettings::where('group_id', $user->group_id)->first();

            // Jika belum ada data di database, kirimkan default mode
            if (!$setting) {
                return ResponseFormatter::success([
                    'acs_mode' => 'default',
                    'acs_url' => '',
                    'acs_port' => '',
                    'acs_username' => '',
                ], 'Pengaturan default dimuat', 200);
            }

            // Keamanan: Sembunyikan password agar tidak bocor ke frontend (Network Tab)
            // Toh di frontend, password hanya dikirim jika user ingin mengubahnya.
            $setting->makeHidden(['acs_password']);

            return ResponseFormatter::success($setting, 'Pengaturan berhasil dimuat', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function updateMode(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $request->validate([
                'acs_mode' => 'required|in:default,mandiri'
            ]);

            $setting = GlobalSettings::where('group_id', $user->group_id)->first();
            if (!$setting) {
                $setting = new GlobalSettings();
                $setting->group_id = $user->group_id;
            }

            $setting->acs_mode = $request->acs_mode;
            $setting->save();

            return ResponseFormatter::success($setting, 'Mode ACS berhasil diubah ke ' . $request->acs_mode, 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function saveMandiriConfig(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $request->validate([
                'acs_url' => 'required|string',
                'acs_port' => 'nullable|string',
                'acs_username' => 'required|string',
                'acs_password' => 'nullable|string',
            ]);

            $setting = GlobalSettings::where('group_id', $user->group_id)->first();
            if (!$setting) {
                $setting = new GlobalSettings();
                $setting->group_id = $user->group_id;
            }

            $setting->acs_url = $request->acs_url;
            $setting->acs_port = $request->acs_port;
            $setting->acs_username = $request->acs_username;

            // Update password hanya jika diisi (agar tidak menimpa dengan null jika form dikosongkan saat update)
            if ($request->filled('acs_password')) {
                $setting->acs_password = $request->acs_password;
            }

            $setting->save();

            return ResponseFormatter::success($setting, 'Konfigurasi ACS Mandiri berhasil disimpan', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    // ==========================================
    // BAGIAN INTERAKSI GENIEACS
    // ==========================================

    public function byGroup(Request $request, GenieAcsService $genie)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $groupId = $request->get('group_id', $user->group_id);

            // 1. Cek setting untuk mengetahui mode yang sedang aktif
            $setting = GlobalSettings::where('group_id', $groupId)->first() ?? GlobalSettings::first();
            $isMandiri = $setting && $setting->acs_mode === 'mandiri';

            // 2. Ambil data sesuai mode
            if ($isMandiri) {
                // ⚙️ Terapkan config khusus Mandiri (URL, Port, User, Pass)
                $genie->setCustomConfig(
                    $setting->acs_url,
                    $setting->acs_port,
                    $setting->acs_username,
                    $setting->acs_password
                );

                // 📡 Ambil SEMUA data (tanpa filter tag)
                $devices = $genie->getAllDevices();
            } else {
                // 📡 Ambil data BERDASARKAN TAG group_id (Mode Default)
                $devices = $genie->getByGroup($groupId);
            }

            // 🛡️ SAFETY CHECK: Pastikan response adalah array valid.
            // Jika GenieACS kosong/error, kita paksa menjadi array kosong [] agar tidak error 500
            if (!is_array($devices) || (isset($devices['name']) && $devices['name'] === 'Error')) {
                $devices = [];
            }

            // 🔍 Search (optional filter dari response array)
            if (!empty($devices) && $search = $request->get('search')) {
                $devices = array_filter($devices, function ($device) use ($search) {
                    return (
                        stripos($device['_deviceId']['_SerialNumber'] ?? '', $search) !== false ||
                        stripos($device['VirtualParameters']['pppoeUsername'] ?? '', $search) !== false
                    );
                });
            }

            // 🔄 Sort manual (karena bukan query builder)
            $sortField = $request->get('sort_field', '_id');
            $sortDirection = $request->get('sort_direction', 'asc');

            if (!empty($devices)) {
                usort($devices, function ($a, $b) use ($sortField, $sortDirection) {
                    $valA = data_get($a, $sortField);
                    $valB = data_get($b, $sortField);

                    if ($sortDirection === 'desc') {
                        return $valB <=> $valA;
                    }

                    return $valA <=> $valB;
                });
            }

            // 📄 Pagination manual
            $perPage = (int) $request->get('per_page', 10);
            $page = (int) $request->get('page', 1);

            $offset = ($page - 1) * $perPage;
            $paginated = array_slice($devices, $offset, $perPage);

            $result = [
                'data' => array_values($paginated),
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => count($devices),
                'last_page' => $perPage > 0 ? ceil(count($devices) / $perPage) : 1,
            ];

            return ResponseFormatter::success($result, 'Data device berhasil dimuat', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function searchPppoe(Request $request, GenieAcsService $genie)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $request->validate(['pppoe' => 'required|string']);

            // ⚙️ Terapkan config dinamis
            $genie = $this->applyAcsConfig($genie, $user->group_id);

            $data = $genie->searchByPppoe($request->pppoe);

            if (empty($data)) {
                return ResponseFormatter::error(null, 'Data PPPoE "' . $request->pppoe . '" tidak ditemukan di server ACS', 404);
            }

            return ResponseFormatter::success($data, 'Data PPPoE berhasil dimuat', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function searchSn(Request $request, GenieAcsService $genie)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $request->validate(['sn' => 'required|string']);

            // ⚙️ Terapkan config dinamis
            $genie = $this->applyAcsConfig($genie, $user->group_id);

            $data = $genie->searchBySn($request->sn);

            if (empty($data)) {
                return ResponseFormatter::error(null, 'Serial Number "' . $request->sn . '" tidak terdaftar atau sedang offline', 404);
            }

            return ResponseFormatter::success($data, 'Data SN berhasil dimuat', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function addGroup(Request $request, GenieAcsService $genie)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $request->validate([
                'device_id' => 'required|string',
            ]);

            // ⚙️ Terapkan config dinamis
            $genie = $this->applyAcsConfig($genie, $user->group_id);

            // 🔍 cek device dulu di GenieACS
            $device = $genie->getDeviceById($request->device_id);

            if (!$device || empty($device)) {
                return ResponseFormatter::error(null, 'Device tidak ditemukan di GenieACS', 404);
            }

            // ➕ add tag group
            $result = $genie->addGroupTag($request->device_id, $user->group_id);

            return ResponseFormatter::success($result, 'Device berhasil ditambahkan ke group', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function removeGroup(Request $request, GenieAcsService $genie)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $request->validate([
                'device_id' => 'required|string',
            ]);

            // ⚙️ Terapkan config dinamis
            $genie = $this->applyAcsConfig($genie, $user->group_id);

            // ➖ remove tag group (panggil fungsi di GenieAcsService)
            $result = $genie->removeGroupTag($request->device_id, $user->group_id);

            return ResponseFormatter::success($result, 'Perangkat berhasil dihapus dari monitoring', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
