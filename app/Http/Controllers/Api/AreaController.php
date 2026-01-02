<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ActivityLogged;
use App\Helpers\ResponseFormatter;
use App\Models\Area as ModelArea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) {
            return $user;
        }

        $id = Auth::id();
        if ($id) {
            return User::find($id);
        }

        return null;
    }

    /**
     * 🔥 GET SUPERADMIN AREAS (Public - untuk dropdown signup)
     *
     * GET /api/v1/areas/superadmin-areas
     *
     * Response:
     * {
     *   "data": {
     *     "areas": [...],
     *     "default_area_id": 1
     *   }
     * }
     */
    public function getSuperadminAreas(Request $request)
    {
        try {
            $areas = ModelArea::where('group_id', 1)
                ->select('id', 'name', 'area_code', 'is_primary', 'created_at')
                ->orderBy('name', 'asc')
                ->get();

            // Cari primary area
            $primaryArea = $areas->firstWhere('is_primary', true);

            $data = [
                'areas' => $areas,
                'default_area_id' => $primaryArea?->id,
                'total' => $areas->count()
            ];

            return ResponseFormatter::success($data, 'Data area superadmin berhasil dimuat', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/areas
     * Ambil daftar area berdasarkan role user yang login
     */
    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return ResponseFormatter::error(null, 'Unauthorized', 401);
            }

            $query = ModelArea::select('id', 'name', 'area_code', 'group_id', 'is_primary', 'created_at')
                ->with(['assignedTechnicians'])
                ->withCount(['opticals', 'connection']);

            // 🔥 Filter berdasarkan role
            if (in_array($user->role, ['teknisi', 'kasir'])) {
                // Teknisi/Kasir: hanya area yang di-assign
                $assignedIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id');
                $query->whereIn('id', $assignedIds);
            } else {
                // Superadmin, Mitra, Admin: filter by group_id
                $query->where('group_id', $user->group_id);
            }

            // 🔍 Search
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('area_code', 'like', "%{$search}%");
                });
            }

            // 🔄 Sort
            $sortField = $request->get('sort_field', 'id');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = $request->get('per_page', 10);
            $areas = $query->paginate($perPage);

            return ResponseFormatter::success($areas, 'Data area berhasil dimuat', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/areas
     * Tambah area baru
     */
    public function store(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return ResponseFormatter::error(null, 'Unauthorized', 401);
            }

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('areas')->where(fn($query) => $query->where('group_id', $user->group_id))
                ],
                'area_code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('areas')->where(fn($query) => $query->where('group_id', $user->group_id))
                ],
                'is_primary' => 'nullable|boolean' // 🔥 NEW
            ]);

            // 🔥 Jika is_primary = true, set area lain jadi false
            if (isset($validated['is_primary']) && $validated['is_primary']) {
                ModelArea::where('group_id', $user->group_id)
                    ->update(['is_primary' => false]);
            }

            $newArea = ModelArea::create([
                'group_id' => $user->group_id,
                'name' => $validated['name'],
                'area_code' => $validated['area_code'],
                'is_primary' => $validated['is_primary'] ?? false
            ]);

            ActivityLogged::dispatch('CREATE', null, $newArea);

            return ResponseFormatter::success($newArea, 'Data area berhasil ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseFormatter::error($e->errors(), 'Validasi gagal', 422);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * 🔥 PUT /api/v1/areas/{id}
     * Update area (termasuk set primary)
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return ResponseFormatter::error(null, 'Unauthorized', 401);
            }

            $area = ModelArea::where('id', $id)
                ->where('group_id', $user->group_id)
                ->firstOrFail();

            $validated = $request->validate([
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('areas')->where(
                        fn($query) =>
                        $query->where('group_id', $user->group_id)
                    )->ignore($id)
                ],
                'area_code' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('areas')->where(
                        fn($query) =>
                        $query->where('group_id', $user->group_id)
                    )->ignore($id)
                ],
                'is_primary' => 'sometimes|boolean'
            ]);

            // 🔥 Jika is_primary diubah jadi true, set area lain jadi false
            if (isset($validated['is_primary']) && $validated['is_primary']) {
                ModelArea::where('group_id', $user->group_id)
                    ->where('id', '!=', $id)
                    ->update(['is_primary' => false]);
            }

            $area->update($validated);

            ActivityLogged::dispatch('UPDATE', $area->getOriginal(), $area->fresh());

            return ResponseFormatter::success($area->fresh(), 'Data area berhasil diupdate', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseFormatter::error($e->errors(), 'Validasi gagal', 422);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/areas/assign
     * Assign teknisi atau kasir ke area
     */
    public function assignTechnician(Request $request)
    {
        try {
            $validated = $request->validate([
                'area_id' => 'required|exists:areas,id',
                'technician_ids' => 'array',
                'technician_ids.*' => 'exists:users,id',
            ]);

            $user = $this->getAuthUser();
            $area = ModelArea::findOrFail($validated['area_id']);

            if ($area->group_id !== $user->group_id) {
                return ResponseFormatter::error(null, 'Area tidak ditemukan!', 403);
            }

            $newTechnicians = $validated['technician_ids'] ?? [];

            // 🔹 Sync pivot table
            $area->assignedTechnicians()->sync($newTechnicians);

            ActivityLogged::dispatch('UPDATE', null, [
                'action' => 'assign_technicians',
                'area' => $area->name,
                'assigned' => User::whereIn('id', $newTechnicians)->pluck('name')->toArray(),
            ]);

            return ResponseFormatter::success(
                ['assigned' => $newTechnicians],
                'Data teknisi area berhasil diperbarui',
                200
            );
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/v1/areas/{id}
     * Hapus area
     */
    public function destroy($id)
    {
        try {
            $user = $this->getAuthUser();
            $area = ModelArea::where('id', $id)
                ->where('group_id', $user->group_id)
                ->firstOrFail();

            $deletedData = $area->toArray();
            $area->delete();

            ActivityLogged::dispatch('DELETE', null, $deletedData);

            return ResponseFormatter::success(null, 'Data area berhasil dihapus', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
