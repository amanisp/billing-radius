<?php

namespace App\Http\Controllers;

use App\Events\ActivityLogged;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ActivityLogController extends Controller
{
    public function index()
    {
        return view('pages.logs.index');
    }

    /**
     * DataTables backend for activity logs with hierarchy filter:
     *  - superadmin => see only their own logs
     *  - mitra => see logs for same group (group_id)
     *  - teknisi / kasir => see logs for same group (group_id)
     */
    public function getData(Request $request)
    {
        try {
            Log::info('DataTables request received', $request->all());

            $user = Auth::user();

            // base query builder (no ->get() yet)
            $query = ActivityLog::query();

            //
            // HIERARCHY FILTER
            //
            // superadmin => only own logs
            // everyone else => see logs with same group_id (preferred)
            //
            if (!empty($user)) {
                $role = strtolower((string) ($user->role ?? ''));

                if ($role === 'superadmin') {
                    // superadmin: only their own logs
                    if (Schema::hasColumn('activity_logs', 'user_id')) {
                        $query->where('user_id', $user->id);
                    } else {
                        $query->where('username', $user->name);
                    }
                } else {
                    // mitra, teknisi, kasir, etc => filter by group_id
                    if (Schema::hasColumn('activity_logs', 'group_id')) {
                        $query->where('group_id', $user->group_id);
                    } else {
                        // fallback: find usernames in same group (slower)
                        $names = User::where('group_id', $user->group_id)
                            ->pluck('name')
                            ->filter()
                            ->values()
                            ->toArray();

                        if (!empty($names)) {
                            $query->whereIn('username', $names);
                        } else {
                            // last-resort fallback: current user's logs only
                            if (Schema::hasColumn('activity_logs', 'user_id')) {
                                $query->where('user_id', $user->id);
                            } else {
                                $query->where('username', $user->name);
                            }
                        }
                    }
                }
            } else {
                // unauthenticated: return nothing (route should be protected)
                $query->whereNull('id');
            }

            // UI filters (operation, role)
            if ($request->filled('operation_type')) {
                $query->where('operation', $request->input('operation_type'));
            }
            if ($request->filled('user_role')) {
                $query->where('role', $request->input('user_role'));
            }

            // Global search
            $globalSearch = $request->input('search.value');
            if ($globalSearch) {
                $query->where(function ($q) use ($globalSearch) {
                    $q->where('operation', 'like', "%{$globalSearch}%")
                        ->orWhere('table_name', 'like', "%{$globalSearch}%")
                        ->orWhere('username', 'like', "%{$globalSearch}%")
                        ->orWhere('role', 'like', "%{$globalSearch}%")
                        ->orWhere('ip_address', 'like', "%{$globalSearch}%")
                        ->orWhere('session_id', 'like', "%{$globalSearch}%")
                        ->orWhere('details', 'like', "%{$globalSearch}%");
                });
            }

            // DataTables paging & ordering
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 1);

            $orderColIndex = (int) $request->input('order.0.column', 0);
            $orderDir = $request->input('order.0.dir', 'desc');
            if (!in_array($orderDir, ['asc', 'desc'])) $orderDir = 'desc';

            $columnsMap = [
                0 => 'time',
                1 => 'operation',
                2 => 'table_name',
                3 => 'username',
                4 => 'role',
                5 => 'ip_address',
                6 => 'session_id',
                7 => 'details',
            ];
            $orderBy = $columnsMap[$orderColIndex] ?? 'created_at';
            if ($orderBy === 'time') {
                $query->orderByRaw("COALESCE(time, created_at) {$orderDir}");
            } else {
                $query->orderBy($orderBy, $orderDir);
            }

            // counts & fetch
            $recordsTotal = ActivityLog::count();
            $recordsFiltered = (clone $query)->count();
            $rows = $query->skip($start)->take($length)->get();

            // format rows
            $data = $rows->map(function ($r) {
                $detailsRaw = $r->details;
                $detailsStr = is_array($detailsRaw) || is_object($detailsRaw)
                    ? json_encode($detailsRaw, JSON_UNESCAPED_UNICODE)
                    : (string) ($detailsRaw ?? '');

                $timeValue = $r->time
                    ? ($r->time instanceof \Carbon\Carbon ? $r->time->format('Y-m-d H:i:s') : $r->time)
                    : ($r->created_at instanceof \Carbon\Carbon ? $r->created_at->format('Y-m-d H:i:s') : $r->created_at);

                return [
                    'time' => $timeValue,
                    'operation' => $r->operation ?? '',
                    'table_name' => $r->table_name ?? '',
                    'username' => $r->username ?? '',
                    'role' => $r->role ?? '',
                    'ip_address' => $r->ip_address ?? '',
                    'session_id' => $r->session_id ?? '',
                    'details' => Str::limit($detailsStr, 200),
                    'raw_details' => $detailsStr,
                    'id' => $r->id,
                ];
            })->toArray();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::error('DataTables getData error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'An error occurred while loading data: ' . $e->getMessage()
            ], 500);
        }
    }

    private static function getUser(?string $username = null, ?string $role = null): array
    {
        $user = Auth::user();
        $username = $username ?? ($user->name ?? null);
        $role = $role ?? ($user->role ?? null);
        return [$username, $role];
    }

    public static function logCreate($data, ?string $table = null, ?string $username = null, ?string $role = null)
    {
        [$username, $role] = self::getUser($username, $role);

        ActivityLogged::dispatch(
            'CREATE',
            $table,
            is_array($data) ? $data : (method_exists($data, 'toArray') ? $data->toArray() : (array)$data),
            $username,
            $role
        );
    }
    public static function logImportStart($data, ?string $table = null, ?string $username = null, ?string $role = null)
    {
        [$username, $role] = self::getUser($username, $role);

        ActivityLogged::dispatch(
            'IMPORT_START',
            $table,
            is_array($data) ? $data : (method_exists($data, 'toArray') ? $data->toArray() : (array)$data),
            $username,
            $role
        );
    }
    public static function logImportSuccesswithWarnings($data, ?string $table = null, ?string $username = null, ?string $role = null)
    {
        [$username, $role] = self::getUser($username, $role);

        ActivityLogged::dispatch(
            'IMPORT_SUCCESS_WITH_WARNINGS',
            $table,
            is_array($data) ? $data : (method_exists($data, 'toArray') ? $data->toArray() : (array)$data),
            $username,
            $role
        );
    }
    public static function logImportSuccess($data, ?string $table = null, ?string $username = null, ?string $role = null)
    {
        [$username, $role] = self::getUser($username, $role);

        ActivityLogged::dispatch(
            'IMPORT_SUCCESS',
            $table,
            is_array($data) ? $data : (method_exists($data, 'toArray') ? $data->toArray() : (array)$data),
            $username,
            $role
        );
    }

    public static function logUpdate($oldData, ?string $table = null, $newData = [], ?string $username = null, ?string $role = null)
    {
        [$username, $role] = self::getUser($username, $role);

        ActivityLogged::dispatch(
            'UPDATE',
            $table,
            [
                'old' => is_array($oldData) ? $oldData : (method_exists($oldData, 'toArray') ? $oldData->toArray() : (array)$oldData),
                'new' => is_array($newData) ? $newData : (method_exists($newData, 'toArray') ? $newData->toArray() : (array)$newData)
            ],
            $username,
            $role
        );
    }

    public static function logDelete($data, ?string $table = null, ?string $username = null, ?string $role = null)
    {
        [$username, $role] = self::getUser($username, $role);

        ActivityLogged::dispatch(
            'DELETE',
            $table,
            is_array($data) ? $data : (method_exists($data, 'toArray') ? $data->toArray() : (array)$data),
            $username,
            $role
        );
    }

    public static function logCreateF($data, ?string $table = null, ?string $username = null, ?string $role = null)
    {
        [$username, $role] = self::getUser($username, $role);

        ActivityLogged::dispatch(
            'CREATE_FAILED',
            $table,
            is_array($data) ? $data : (method_exists($data, 'toArray') ? $data->toArray() : (array)$data),
            $username,
            $role
        );
    }

        public static function logImportF($data, ?string $table = null, ?string $username = null, ?string $role = null)
    {
        [$username, $role] = self::getUser($username, $role);

        ActivityLogged::dispatch(
            'IMPORT_FAILED',
            $table,
            is_array($data) ? $data : (method_exists($data, 'toArray') ? $data->toArray() : (array)$data),
            $username,
            $role
        );
    }

    /**
     * Delete single log by id (used in UI admin)
     */
    public function deleteLog($id)
    {
        try {
            $log = ActivityLog::findOrFail($id);
            $log->delete();

            Log::info('Activity log deleted', ['id' => $id]);

            return response()->json(['success' => true, 'message' => 'Log deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Error deleting activity log', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to delete log'], 500);
        }
    }
}
