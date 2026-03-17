<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Connection;
use App\Models\Groups;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EosController extends Controller
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

            if (!$user || $user->role !== 'superadmin') {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $query = User::withCount('opticals')->withCount('members')->where('role', 'mitra');

            // 🔍 Search
            if ($search = $request->get('search')) {
                $query->where('name', 'like', "%{$search}%");
            }

            // 🔄 Sort
            $sortField = $request->get('sort_field', 'id');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = $request->get('per_page', 5);
            $opticals = $query->paginate($perPage);

            return ResponseFormatter::success($opticals, 'Data eos berhasil dimuat', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $user = $this->getAuthUser();
            $userDeleted = User::where('id', $id)->firstOrFail();

            if (!$user || $user->role !== 'superadmin') {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $groupId = $userDeleted->group_id;

            // 🔥 Hapus semua data berdasarkan group_id
            Groups::where('id', $groupId)->delete();

            DB::connection('radius')->table('nas')
                ->where('group_id', $groupId)
                ->delete();

            DB::connection('radius')->table('radcheck')
                ->where('group_id', $groupId)
                ->delete();

            DB::connection('radius')->table('radgroupcheck')
                ->where('group_id', $groupId)
                ->delete();

            DB::connection('radius')->table('radgroupreply')
                ->where('group_id', $groupId)
                ->delete();

            DB::connection('radius')->table('radreply')
                ->where('group_id', $groupId)
                ->delete();

            DB::connection('radius')->table('radusergroup')
                ->where('group_id', $groupId)
                ->delete();

            DB::commit();

            ActivityLogController::logCreate(
                ['action' => 'destroy', 'status' => 'success', 'group_id' => $groupId],
                'User Group Delete'
            );

            return ResponseFormatter::success(null, 'Semua data group berhasil dihapus', 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            ActivityLogController::logCreateF(
                ['action' => 'destroy', 'error' => $th->getMessage()],
                'User Group Delete'
            );

            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
