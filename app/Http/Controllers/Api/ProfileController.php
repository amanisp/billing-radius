<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
use App\Events\ActivityLogged;
use App\Helpers\ResponseFormatter;
use App\Models\Profiles;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
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
     * GET /api/profiles
     * List profiles dengan pagination, search, dan sort
     */
    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $query = Profiles::where('group_id', $user->group_id);

            // 🔍 Search
            if ($search = $request->get('search')) {
                $query->where('name', 'like', "%{$search}%");
            }

            // 🔄 Sort
            $sortField = $request->get('sort_field', 'id');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = $request->get('per_page', 15);
            $profiles = $query->paginate($perPage);


            return ResponseFormatter::success($profiles, 'Data profiles berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }

    /**
     * POST /api/profiles
     * Create new profile
     */
    public function store(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('profiles')->where(fn($q) => $q->where('group_id', $user->group_id))
                ],
                'price' => 'required|numeric|min:0',
                'rate_rx' => 'string|required',
                'rate_tx' => 'string|required',
                'burst_rx' => 'nullable|string',
                'burst_tx' => 'nullable|string',
                'threshold_rx' => 'nullable|string',
                'threshold_tx' => 'nullable|string',
                'time_rx' => 'nullable|string',
                'time_tx' => 'nullable|string',
                'priority' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $profile = Profiles::create([
                'name' => $validated['name'],
                'group_id' => $user->group_id,
                'price' => (int) str_replace('.', '', $request->price),
                'rate_rx' => $validated['rate_rx'] ?? '0',
                'rate_tx' => $validated['rate_tx'] ?? '0',
                'burst_rx' => $validated['burst_rx'] ?? '0',
                'burst_tx' => $validated['burst_tx'] ?? '0',
                'threshold_rx' => $validated['threshold_rx'] ?? '0',
                'threshold_tx' => $validated['threshold_tx'] ?? '0',
                'time_rx' => $validated['time_rx'] ?? '0',
                'time_tx' => $validated['time_tx'] ?? '0',
                'priority' => $validated['priority'] ?? '8',
            ]);

            // Create radius group reply
            DB::connection('radius')->table('radgroupreply')->insert([
                'groupname' => $profile->name . '-' . $user->group_id,
                'attribute' => 'Mikrotik-Rate-Limit',
                'op' => ':=',
                'value' => "{$profile->rate_tx}/{$profile->rate_rx} {$profile->burst_tx}/{$profile->burst_rx} {$profile->threshold_tx}/{$profile->threshold_rx} {$profile->time_tx}/{$profile->time_rx} {$profile->priority}",
                'group_id' => $user->group_id
            ]);

            DB::commit();

            ActivityLogController::logCreate(['action' => 'store', 'status' => 'success'], 'profiles');
            return ResponseFormatter::success($profile, 'Profile berhasil ditambahkan', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            ActivityLogController::logCreateF(['action' => 'store', 'error' => $th->getMessage()], 'profiles');
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }

    /**
     * PUT /api/profiles/{id}
     * Update profile
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $this->getAuthUser();
            $profile = Profiles::where('id', $id)->firstOrFail();

            if ($profile->group_id !== $user->group_id) {
                return response()->json(['message' => 'Profile tidak ditemukan!'], 403);
            }

            $oldData = $profile->toArray();

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('profiles')->ignore($id)->where(fn($q) => $q->where('group_id', $user->group_id))
                ],
                'price' => 'required|numeric|min:0',
                'rate_rx' => 'nullable|string',
                'rate_tx' => 'nullable|string',
                'burst_rx' => 'nullable|string',
                'burst_tx' => 'nullable|string',
                'threshold_rx' => 'nullable|string',
                'threshold_tx' => 'nullable|string',
                'time_rx' => 'nullable|string',
                'time_tx' => 'nullable|string',
                'priority' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $profile->update([
                'name' => $validated['name'],
                'price' => (int) str_replace('.', '', $request->price),
                'rate_rx' => $validated['rate_rx'] ?? '0',
                'rate_tx' => $validated['rate_tx'] ?? '0',
                'burst_rx' => $validated['burst_rx'] ?? '0',
                'burst_tx' => $validated['burst_tx'] ?? '0',
                'threshold_rx' => $validated['threshold_rx'] ?? '0',
                'threshold_tx' => $validated['threshold_tx'] ?? '0',
                'time_rx' => $validated['time_rx'] ?? '0',
                'time_tx' => $validated['time_tx'] ?? '0',
                'priority' => $validated['priority'] ?? '8',
            ]);

            // Update radius group reply
            DB::connection('radius')->table('radgroupreply')
                ->where('groupname', $oldData['name'] . '-' . $user->group_id)
                ->where('group_id', $user->group_id)
                ->update([
                    'groupname' => $profile->name . '-' . $user->group_id,
                    'value' => "{$profile->rate_rx}/{$profile->rate_tx} {$profile->burst_rx}/{$profile->burst_tx} {$profile->threshold_rx}/{$profile->threshold_tx} {$profile->time_rx}/{$profile->time_tx} {$profile->priority}",
                ]);

            DB::commit();

            ActivityLogController::logCreate(['action' => 'update', 'status' => 'success'], 'profiles');
            return ResponseFormatter::success($profile, 'Profile berhasil diperbarui', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            ActivityLogController::logCreateF(['action' => 'update', 'error' => $th->getMessage()], 'profiles');
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }

    /**
     * DELETE /api/profiles/{id}
     * Delete profile
     */
    public function destroy($id)
    {
        try {
            $user = $this->getAuthUser();
            $profile = Profiles::where('id', $id)->firstOrFail();

            if ($profile->group_id !== $user->group_id) {
                return response()->json(['message' => 'Profile tidak ditemukan!'], 200);
            }

            DB::beginTransaction();

            // Delete radius group reply
            DB::connection('radius')->table('radgroupreply')
                ->where('groupname', $profile->name . '-' . $profile->group_id)
                ->where('group_id', $profile->group_id)
                ->delete();

            $deletedData = $profile->toArray();
            $profile->delete();

            DB::commit();

            ActivityLogController::logCreate(['action' => 'destroy', 'status' => 'success'], 'profiles');
            return ResponseFormatter::success($deletedData, 'Profile berhasil dihapus', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            ActivityLogController::logCreateF(['action' => 'destroy', 'error' => $th->getMessage()], 'profiles');
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }
}
