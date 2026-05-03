<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GenieAcsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\json;

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

    public function byGroup(Request $request, GenieAcsService $genie)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $groupId = $request->get('group_id', $user->group_id);

            // 📡 ambil data dari GenieACS
            $devices = $genie->getByGroup($groupId);

            // 🔍 Search (optional filter dari response array)
            if ($search = $request->get('search')) {
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

            usort($devices, function ($a, $b) use ($sortField, $sortDirection) {
                $valA = data_get($a, $sortField);
                $valB = data_get($b, $sortField);

                if ($sortDirection === 'desc') {
                    return $valB <=> $valA;
                }

                return $valA <=> $valB;
            });

            // 📄 Pagination manual (karena bukan Eloquent)
            $perPage = (int) $request->get('per_page', 10);
            $page = (int) $request->get('page', 1);

            $offset = ($page - 1) * $perPage;
            $paginated = array_slice($devices, $offset, $perPage);

            $result = [
                'data' => array_values($paginated),
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => count($devices),
                'last_page' => ceil(count($devices) / $perPage),
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
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $request->validate([
                'pppoe' => 'required|string'
            ]);

            $data = $genie->searchByPppoe($request->pppoe);

            // Cek jika data kosong atau null
            if (empty($data)) {
                return ResponseFormatter::error(
                    null,
                    'Data PPPoE "' . $request->pppoe . '" tidak ditemukan di server ACS',
                    404
                );
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
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $request->validate([
                'sn' => 'required|string'
            ]);

            $data = $genie->searchBySn($request->sn);

            // Cek jika data kosong atau null
            if (empty($data)) {
                return ResponseFormatter::error(
                    null,
                    'Serial Number "' . $request->sn . '" tidak terdaftar atau sedang offline',
                    404
                );
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
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $request->validate([
                'device_id' => 'required|string',
            ]);

            // 🔍 cek device dulu di GenieACS
            $device = $genie->getDeviceById($request->device_id);


            if (!$device || empty($device)) {
                return ResponseFormatter::error(
                    null,
                    'Device tidak ditemukan di GenieACS',
                    404
                );
            }

            // ➕ add tag group
            $result = $genie->addGroupTag(
                $request->device_id,
                $user->group_id
            );

            return ResponseFormatter::success(
                $result,
                'Device berhasil ditambahkan ke group',
                200
            );
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
