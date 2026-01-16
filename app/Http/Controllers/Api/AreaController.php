<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
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
     * GET /api/areas
     * Ambil daftar area berdasarkan role user.
     */
    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $query = ModelArea::select('id', 'name', 'area_code', 'group_id', 'created_at')
                ->with(['assignedTechnicians']) // tambahkan ini
                ->withCount(['opticals', 'connection']);


            if (in_array($user->role, ['teknisi', 'kasir'])) {
                $assignedIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id');
                $query->whereIn('id', $assignedIds);
            } else {
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
            $perPage = $request->get('per_page', 5);
            $areas = $query->paginate($perPage);

            ActivityLogController::logCreate(['action' => 'index', 'status' => 'success'], 'areas');
            return ResponseFormatter::success($areas, 'Data area berhasil dimuat');
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'index', 'error' => $th->getMessage()], 'areas');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }



    /**
     * POST /api/areas
     * Tambah area baru.
     */
    public function store(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $validate = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('areas')->where(fn($query) => $query->where('group_id', $user->group_id))
                ],
                'area_code' => ['required', 'string', 'max:255', Rule::unique('areas')->where(fn($query) => $query->where('group_id', $user->group_id))]
            ]);

            $newArea = ModelArea::create([
                'group_id' => $user->group_id,
                'name' => $validate['name'],
                'area_code' => $validate['area_code']
            ]);

            ActivityLogController::logCreate(['action' => 'store', 'status' => 'success'], 'areas');
            return ResponseFormatter::success($newArea, 'Data berhasil ditambahkan', 200);
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'store', 'error' => $th->getMessage()], 'areas');
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }

    /**
     * POST /api/areas/assign
     * Assign teknisi atau kasir ke area.
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
                return response()->json(['message' => 'Area tidak ditemukan!'], 403);
            }

            $newTechnicians = $validated['technician_ids'] ?? [];

            // 🔹 Update pivot tanpa duplikat & sinkronisasi otomatis
            $area->assignedTechnicians()->sync($newTechnicians);

            ActivityLogController::logCreate(['action' => 'assignTechnician', 'status' => 'success'], 'areas');

            return ResponseFormatter::success(
                ['assigned' => $newTechnicians],
                'Data teknisi area berhasil diperbarui',
                200
            );
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'assignTechnician', 'error' => $th->getMessage()], 'areas');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }


    /**
     * DELETE /api/areas/{id}
     * Hapus area.
     */
    public function destroy($id)
    {
        try {
            $user = $this->getAuthUser();
            $area = ModelArea::where('id', $id)->firstOrFail();

            if ($area->group_id !== $user->group_id) {
                return response()->json(['message' => 'Area tidak ditemukan!'], 403);
            }

            $deletedData = $area;
            $area->delete();

            ActivityLogController::logCreate(['action' => 'destroy', 'status' => 'success'], 'areas');

            return ResponseFormatter::success($area, 'Data berhasil dihapus', 200);
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'destroy', 'error' => $th->getMessage()], 'areas');
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }
}
