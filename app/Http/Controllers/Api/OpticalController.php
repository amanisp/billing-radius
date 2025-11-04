<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ActivityLogged;
use App\Helpers\ResponseFormatter;
use App\Models\OpticalDist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OpticalController extends Controller
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
     * GET /api/opticals
     * List optical dengan pagination, search, sort
     */
    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $query = OpticalDist::where('group_id', $user->group_id)->withCount(['connection']);

            // 🔍 Search
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('device_name', 'like', "%{$search}%")
                        ->orWhere('ip_public', 'like', "%{$search}%");
                });
            }

            // 🔄 Sort
            $sortField = $request->get('sort_field', 'id');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = $request->get('per_page', 5);
            $opticals = $query->paginate($perPage);

            return ResponseFormatter::success($opticals, 'Data optical berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * POST /api/opticals
     * Tambah optical baru
     */
    public function store(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $validate = $request->validate(
                [
                    'name'     => ['required', 'string', 'max:255', Rule::unique('optical_dists')->where(fn($q) => $q->where('group_id', $user->group_id))],
                    'area_id'  => 'required|exists:areas,id',
                    'lat'      => 'max:255',
                    'lng'      => 'max:255',
                    'capacity' => 'required|max:15',
                    'type'     => 'required'
                ]
            );

            $newArea = OpticalDist::create([
                'group_id' => $user->group_id,
                'name' => $validate['name'],
                'area_id' => $validate['area_id'],
                'lat' => $validate['lat'],
                'lng' => $validate['lng'],
                'capacity' => $validate['capacity'],
                'type' => $validate['type']
            ]);

            ActivityLogged::dispatch('CREATE', null, $newArea);

            return ResponseFormatter::success($newArea, 'Data berhasil ditambahkan', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }

    /**
     * PUT /api/opticals/{id}
     * Update optical
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $this->getAuthUser();
            $optical = OpticalDist::where('id', $id)->firstOrFail();

            if ($optical->group_id !== $user->group_id) {
                return response()->json(['message' => 'Data optical tidak ditemukan!'], 403);
            }

            $rules = [
                'name'     => ['required', 'string', 'max:255', Rule::unique('optical_dists')->ignore($id)->where(fn($q) => $q->where('group_id', $user->group_id))],
                'area_id'  => 'required|exists:areas,id',
                'lat'      => 'max:255',
                'lng'      => 'max:255',
                'capacity' => 'required|max:15',
                'type'     => 'required'
            ];

            $validated = $request->validate($rules);

            $optical->update($validated);

            ActivityLogged::dispatch('UPDATE', null, $optical);

            return ResponseFormatter::success($optical, 'Data optical berhasil diperbarui', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/opticals/{id}
     * Hapus optical
     */
    public function destroy($id)
    {
        try {
            $user = $this->getAuthUser();
            $optical = OpticalDist::where('id', $id)->firstOrFail();

            if ($optical->group_id !== $user->group_id) {
                return response()->json(['message' => 'Data optical tidak ditemukan!'], 403);
            }

            $deletedData = $optical;
            $optical->delete();

            ActivityLogged::dispatch('DELETE', null, $deletedData);

            return ResponseFormatter::success($deletedData, 'Data optical berhasil dihapus', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
