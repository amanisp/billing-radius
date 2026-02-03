<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LogsController extends Controller
{
    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }

    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $query = ActivityLog::query();

            //
            // 🔒 Hierarchy Filter
            //
            $role = strtolower((string) ($user->role ?? ''));

            if ($role === 'superadmin') {
                // Superadmin: hanya lihat log miliknya sendiri
                if (Schema::hasColumn('activity_logs', 'user_id')) {
                    $query->where('user_id', $user->id);
                } else {
                    $query->where('username', $user->name);
                }
            } else {
                // Mitra, kasir, teknisi: berdasarkan group_id
                if (Schema::hasColumn('activity_logs', 'group_id')) {
                    $query->where('group_id', $user->group_id);
                } else {
                    $names = User::where('group_id', $user->group_id)
                        ->pluck('name')
                        ->filter()
                        ->values()
                        ->toArray();

                    if (!empty($names)) {
                        $query->whereIn('username', $names);
                    } else {
                        $query->where('username', $user->name);
                    }
                }
            }

            //
            // 🔍 Filter tambahan dari query param
            //
            if ($request->filled('operation_type')) {
                $query->where('operation', $request->input('operation_type'));
            }

            if ($request->filled('user_role')) {
                $query->where('role', $request->input('user_role'));
            }

            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('operation', 'like', "%{$search}%")
                        ->orWhere('table_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('session_id', 'like', "%{$search}%")
                        ->orWhere('details', 'like', "%{$search}%");
                });
            }

            //
            // 🔄 Sort
            //
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            $allowedSorts = [
                'time',
                'operation',
                'table_name',
                'username',
                'role',
                'ip_address',
                'session_id',
                'created_at'
            ];

            if (!in_array($sortField, $allowedSorts)) {
                $sortField = 'created_at';
            }

            if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
                $sortDirection = 'desc';
            }

            if ($sortField === 'time') {
                $query->orderByRaw("COALESCE(time, created_at) {$sortDirection}");
            } else {
                $query->orderBy($sortField, $sortDirection);
            }

            //
            // 📄 Pagination
            //
            $perPage = (int) $request->get('per_page', 10);
            $logs = $query->paginate($perPage);

            //
            // 🧩 Format data
            //
            $logs->getCollection()->transform(function ($r) {
                $detailsRaw = $r->details;
                $detailsStr = is_array($detailsRaw) || is_object($detailsRaw)
                    ? json_encode($detailsRaw, JSON_UNESCAPED_UNICODE)
                    : (string) ($detailsRaw ?? '');

                $timeValue = $r->time
                    ? ($r->time instanceof \Carbon\Carbon ? $r->time->format('Y-m-d H:i:s') : $r->time)
                    : ($r->created_at instanceof \Carbon\Carbon ? $r->created_at->format('Y-m-d H:i:s') : $r->created_at);

                return [
                    'id' => $r->id,
                    'time' => $timeValue,
                    'operation' => $r->operation ?? '',
                    'table_name' => $r->table_name ?? '',
                    'username' => $r->username ?? '',
                    'role' => $r->role ?? '',
                    'ip_address' => $r->ip_address ?? '',
                    'session_id' => $r->session_id ?? '',
                    'details' => Str::limit($detailsStr, 200),
                    'raw_details' => $detailsStr,
                ];
            });



            return ResponseFormatter::success($logs, 'Data aktivitas berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
