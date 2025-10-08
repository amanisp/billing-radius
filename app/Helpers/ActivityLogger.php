<?php

namespace App\Helpers;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    public static function log($operation, $tableName, $details = [], $role = null, $username = null)
    {
        $user = Auth::user();

        ActivityLog::create([
            'time'       => now(),
            'operation'  => $operation,
            'table_name' => $tableName,
            'username'   => $username ?? ($user?->name ?? 'System'),
            'role'       => $role ?? ($user?->role ?? 'N/A'),
            'ip_address' => Request::ip(),
            'session_id' => session()->getId(),
            'details'    => $details,
        ]);
    }
}
