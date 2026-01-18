<?php

namespace App\Http\Controllers\Api;

use App\Events\ActivityLogged;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Controller;
use App\Models\Nas;
use App\Models\Radius\RadGroupCheck;
use App\Models\Radius\RadNas;
use App\Models\Radius\RadReload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NasController extends Controller
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

    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // 🔍 Query dasar
            $query = Nas::select('id', 'name', 'ip_radius', 'ip_router', 'secret', 'group_id', 'created_at')
                ->where('group_id', $user->group_id);

            // 🔍 Search
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%");
                });
            }

            // 🔄 Sort
            $sortField = $request->get('sort_field', 'id');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = $request->get('per_page', 10);
            $vpnUsers = $query->paginate($perPage);

            ActivityLogController::logCreate([
                'action' => 'view_nas_list',
                'total_records' => $vpnUsers->total(),
                'status' => 'success'
            ], 'nas');

            return ResponseFormatter::success($vpnUsers, 'Data NAS berhasil dimuat');
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'view_nas_list', 'error' => $th->getMessage()], 'nas');
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            $groupId = $user->group_id;

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('nas')->where(fn($query) => $query->where('group_id', $groupId))
                ],
                'ip_router'    => 'required|string|ipv4',
                'secret'    => 'required|string|max:255',
            ]);


            $data = Nas::create([
                'name'        => $validated['name'],
                'group_id'    => $groupId,
                'ip_radius'     => '10.137.24.15',
                'ip_router'         => $validated['ip_router'],
                'secret'         => $validated['secret'],
            ]);


            RadNas::create([
                'nasname' => $data->ip_router,
                'shortname' => $data->name,
                'secret' => $data->secret,
                'description' => $data->group_id,
                'group_id'    => $groupId,
            ]);

            RadReload::create([
                'nasipaddress' => $data->ip_router,
                'reloadtime' => now()
            ]);

            RadGroupCheck::create([
                'groupname' => 'mitra_' . $groupId,
                'attribute' => 'NAS-IP-Address',
                'op' => '==',
                'value' => $data->ip_router,
                'group_id' => $groupId
            ]);

            ActivityLogController::logCreate(['action' => 'store', 'status' => 'success'], 'nas');
            return ResponseFormatter::success($data, 'Data NAS berhasil disimpan', 200);
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'store', 'error' => $th->getMessage()], 'nas');
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }

    public function destroy($id)
    {
        try {
            $user = $this->getAuthUser();
            $data = Nas::where('id', $id)->firstOrFail();

            if ($data->group_id !== $user->group_id) {
                return response()->json(['message' => 'NAS tidak ditemukan!'], 403);
            }

            RadNas::where('group_id', $data->group_id)->delete();
            RadGroupCheck::where('group_id', $data->group_id)->delete();
            $data->delete();

            ActivityLogController::logCreate(['action' => 'destroy', 'status' => 'success'], 'nas');
            return ResponseFormatter::success($data, 'Data berhasil dihapus', 200);
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'destroy', 'error' => $th->getMessage()], 'nas');
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }
}
