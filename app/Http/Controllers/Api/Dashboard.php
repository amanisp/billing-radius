<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
use App\Models\Area;
use App\Models\Connection;
use App\Models\OpticalDist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Dashboard extends Controller
{
    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }

    public function stats(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $queryOdp = OpticalDist::where('group_id', $user->group_id);
            $queryArea = Area::where('group_id', $user->group_id);
            $queryConn = Connection::where('group_id', $user->group_id);

            $stats = [
                'odp' => (clone $queryOdp)->where('type', 'ODP')->count(),
                'odc' => (clone $queryOdp)->where('type', 'ODC')->count(),
                'area' => (clone $queryArea)->count(),
                'homepass' => (clone $queryConn)->count(),
            ];



            return ResponseFormatter::success($stats, 'Statistics berhasil dimuat');
        } catch (\Throwable $th) {

            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }

    public function statsPppoe(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $groupName = 'mitra_' . $user->group_id;

            $totalPppoe = DB::connection('radius')
                ->table('radusergroup')
                ->where('groupname', $groupName)
                ->distinct('username')
                ->count('username');

            /**
             * ===============================
             * TOTAL ACTIVE (ONLINE)
             * ===============================
             */
            $totalActive = DB::connection('radius')
                ->table('radacct as ra')
                ->join('radusergroup as rug', 'ra.username', '=', 'rug.username')
                ->where('rug.groupname', $groupName)
                ->whereNull('ra.acctstoptime')
                ->where('ra.acctupdatetime', '>=', now()->subMinutes(120))
                ->distinct('ra.username')
                ->count('ra.username');

            /**
             * ===============================
             * TOTAL OFFLINE
             * ===============================
             */
            $totalOffline = max($totalPppoe - $totalActive, 0);

            $data = [
                'total_pppoe' => $totalPppoe,
                'total_active' => $totalActive,
                'total_offline' => $totalOffline,
            ];



            return ResponseFormatter::success($data, 'PPPoE STatus berhasil dimuat');
        } catch (\Throwable $th) {

            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }
}
