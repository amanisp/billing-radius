<?php

namespace App\Http\Controllers\Api;

use App\Events\ActivityLogged;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Controller;
use App\Models\TechnicianArea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{

    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }

    // GET /api/admin
    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $query = User::where('group_id', $user->group_id)->where('role', 'teknisi');

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

            ActivityLogController::logCreate(['action' => 'index', 'status' => 'success'], 'users');
            return ResponseFormatter::success($opticals, 'Data admin berhasil dimuat', 200);
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'index', 'error' => $th->getMessage()], 'users');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            $validated = $request->validate([
                'name'         => 'required|string|max:255|unique:users,name|unique:groups,name',
                'phone_number' => [
                    'required',
                    'string',
                    'max:15',
                    'regex:/^(?:\+62|62|0)[0-9]{9,13}$/',
                    Rule::unique('users', 'phone_number')
                ],
                'email'        => 'required|string|max:255|unique:users,email',
                'username'     => 'required|unique:users,username',
                'password'     => 'required|min:8',
                'role'         => 'required',
            ]);

            $newUser = User::create([
                'name'         => $validated['name'],
                'email'        => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'role'         => $validated['role'],
                'username'     => $validated['username'],
                'password'     => Hash::make($validated['password']),
                'group_id'     => $user->group_id,
            ]);

            ActivityLogController::logCreate(['action' => 'store', 'status' => 'success'], 'users');

            return ResponseFormatter::success($newUser, 'Data admin berhasil ditambahkan', 200);
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'store', 'error' => $th->getMessage()], 'users');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    // // PUT /api/admin/{id}
    // public function update(Request $request, $id)
    // {
    //     try {
    //         $user = User::findOrFail($id);
    //         $oldData = $user->toArray();

    //         $validated = $request->validate([
    //             'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($user->id)],
    //             'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
    //             'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
    //             'phone_number' => ['nullable', 'string', 'max:255', Rule::unique('users', 'phone_number')->ignore($user->id)],
    //             'password' => 'nullable|string|min:8',
    //             'role' => 'required|string',
    //         ]);

    //         $user->update([
    //             'name' => $validated['name'],
    //             'username' => $validated['username'],
    //             'email' => $validated['email'],
    //             'phone_number' => $validated['phone_number'],
    //             'role' => $validated['role'],
    //         ]);

    //         if (!empty($validated['password'])) {
    //             $user->update(['password' => Hash::make($validated['password'])]);
    //         }

    //         ActivityLogController::logUpdate($oldData, 'users', $user->fresh());

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Data user berhasil diperbarui',
    //             'data' => $user
    //         ], 200);
    //     } catch (\Throwable $th) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $th->getMessage()
    //         ], 500);
    //     }
    // }

    // PUT /api/admin/profile
    // public function updateProfile(Request $request)
    // {
    //     try {
    //         $user = Auth::user();
    //         $oldData = $user->toArray();

    //         $validated = $request->validate([
    //             'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($user->id)],
    //             'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
    //             'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
    //             'phone_number' => ['nullable', 'string', 'max:255', Rule::unique('users', 'phone_number')->ignore($user->id)],
    //             'password' => 'nullable|string|min:8',
    //         ]);

    //         $user->update([
    //             'name' => $validated['name'],
    //             'username' => $validated['username'],
    //             'email' => $validated['email'],
    //             'phone_number' => $validated['phone_number'],
    //         ]);

    //         if (!empty($validated['password'])) {
    //             $user->update(['password' => Hash::make($validated['password'])]);
    //         }

    //         ActivityLogController::logUpdate($oldData, 'users', $user->fresh());

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Profil berhasil diperbarui',
    //             'data' => $user
    //         ], 200);
    //     } catch (\Throwable $th) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $th->getMessage()
    //         ], 500);
    //     }
    // }

    // DELETE /api/admin/{id}
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $deletedData = $user;

            if (isset($user)) {
                $areaList = TechnicianArea::where('user_id', $user->id);
                $areaList->delete();
            }
            $user->delete();

            ActivityLogController::logCreate([
                'user_id' => $id,
                'action' => 'destroy_admin',
                'status' => 'success'
            ], 'users');
            ActivityLogged::dispatch('DELETE', null, $deletedData);

            return ResponseFormatter::success(null, 'Data admin berhasil dihapus', 200);
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF([
                'user_id' => $id ?? null,
                'action' => 'destroy_admin',
                'error' => $th->getMessage()
            ], 'users');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
