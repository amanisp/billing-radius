<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
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

            // 🔍 Query ke tabel radacct (FreeRADIUS)
            $query = DB::connection('radius')->table('radacct as ra')
                ->join('radusergroup as rug', 'ra.username', '=', 'rug.username')
                ->where('rug.groupname', 'mitra_' . $user->group_id) // Filter per mitra
                ->whereNull('ra.acctstoptime') // hanya user yang masih online
                ->where('ra.acctupdatetime', '>=', now()->subMinutes(60)) // aktif 2 jam terakhir
                ->select([
                    'ra.acctsessionid as session_id',
                    'ra.username',
                    'ra.acctstarttime as login_time',
                    'ra.acctupdatetime as last_update',
                    'ra.framedipaddress as ip_address',
                    'ra.callingstationid as mac_address',
                    'ra.acctinputoctets as upload',
                    'ra.acctoutputoctets as download',
                    'ra.acctsessiontime as uptime'
                ]);

            // 🔍 Search filter opsional
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('ra.username', 'like', "%{$search}%")
                        ->orWhere('ra.framedipaddress', 'like', "%{$search}%")
                        ->orWhere('ra.callingstationid', 'like', "%{$search}%");
                });
            }

            // 🔄 Sort
            $sortField = $request->get('sort_field', 'ra.acctstarttime');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = $request->get('per_page', 10);
            $data = $query->paginate($perPage);

            // 🔢 Format data agar lebih mudah dibaca di frontend
            $data->getCollection()->transform(function ($row) {
                return [
                    'session_id' => $row->session_id,
                    'username' => $row->username,
                    'login_time' => $row->login_time,
                    'last_update' => $row->last_update,
                    'ip_address' => $row->ip_address,
                    'mac_address' => $row->mac_address,
                    'upload' => $this->formatBytes($row->upload),
                    'download' => $this->formatBytes($row->download),
                    'uptime' => $this->formatUptime($row->uptime),
                ];
            });

            ActivityLogController::logCreate([
                'action' => 'view_pppoe_sessions',
                'total_records' => $data->total(),
                'status' => 'success'
            ], 'pppoe_sessions');

            return ResponseFormatter::success($data, 'Data PPPoE Online berhasil dimuat');
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF([
                'action' => 'view_pppoe_sessions',
                'error' => $th->getMessage()
            ], 'pppoe_sessions');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * Format byte ke format human readable
     */
    private function formatBytes($bytes, $precision = 2)
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(log($bytes, 1024));
        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }

    /**
     * Format uptime ke hari dan jam
     */
    private function formatUptime($seconds)
    {
        $days = floor($seconds / 86400);
        $time = gmdate("H:i:s", $seconds % 86400);
        return ($days > 0 ? "{$days}d " : "") . $time;
    }
}
