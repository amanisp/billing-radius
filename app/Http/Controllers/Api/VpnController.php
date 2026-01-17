<?php

namespace App\Http\Controllers\Api;

use App\Events\ActivityLogged;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Controller;
use App\Helpers\ResponseFormatter;
use App\Models\Radius\RadCheck;
use App\Models\Radius\RadReply;
use App\Models\Radius\RadUserGroup;
use App\Models\User;
use App\Models\VpnUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class VpnController extends Controller
{
    private $ip_range_start = '172.31.18.2';
    private $ip_range_end = '172.31.19.254';

    private function getAvailableIp()
    {
        $start = ip2long($this->ip_range_start);
        $end = ip2long($this->ip_range_end);

        for ($ip = $start; $ip <= $end; $ip++) {
            $ip_address = long2ip($ip);
            if (!VpnUsers::where('ip_address', $ip_address)->exists()) {
                return $ip_address;
            }
        }

        return null; // Tidak ada IP tersedia
    }

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
            $query = VpnUsers::select('id', 'name', 'username', 'ip_address', 'password', 'group_id', 'created_at')
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
                'action' => 'view_vpn_list',
                'total_records' => $vpnUsers->total(),
                'status' => 'success'
            ], 'vpn_users');

            return ResponseFormatter::success($vpnUsers, 'Data VPN berhasil dimuat');
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'view_vpn_list', 'error' => $th->getMessage()], 'vpn_users');
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
                    Rule::unique('vpn_users')->where(fn($q) => $q->where('group_id', $groupId))
                ],
                'username' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('vpn_users')->where(fn($q) => $q->where('group_id', $groupId))
                ],
                'password' => 'required|string|max:255',
            ]);

            $ip_address = $this->getAvailableIp();
            if (!$ip_address) {
                return ResponseFormatter::error(null, 'IP Pool sudah penuh!', 400);
            }

            $data = VpnUsers::create([
                'name'       => $validated['name'],
                'group_id'   => $groupId,
                'username'   => $validated['username'],
                'password'   => $validated['password'],
                'ip_address' => $ip_address,
            ]);

            RadCheck::create([
                'username' => $data->username,
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'value' => $data->password,
                'group_id' => $groupId,
            ]);

            RadUserGroup::create([
                'username'  => $data->username,
                'groupname' => 'nas_vpn',
                'priority'  => 1,
                'group_id'  => $groupId,
            ]);

            RadReply::create([
                'username'  => $data->username,
                'attribute' => 'Framed-IP-Address',
                'op'        => ':=',
                'value'     => $ip_address,
                'group_id'  => $groupId,
            ]);

            ActivityLogController::logCreate(['action' => 'store', 'status' => 'success'], 'vpn_users');
            return ResponseFormatter::success($data, 'Data VPN berhasil disimpan', 200);
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'store', 'error' => $th->getMessage()], 'vpn_users');
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }

    public function destroy($id)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            $profile = VpnUsers::where('group_id', $user->group_id)->findOrFail($id);

            RadCheck::where('username', $profile->username)->delete();
            RadUserGroup::where('username', $profile->username)->delete();
            RadReply::where('username', $profile->username)->delete();
            $profile->delete();

            ActivityLogController::logCreate(['action' => 'destroy', 'status' => 'success'], 'vpn_users');
            return ResponseFormatter::success(null, 'VPN berhasil dihapus dari sistem.');
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'destroy', 'error' => $th->getMessage()], 'vpn_users');
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }
}
