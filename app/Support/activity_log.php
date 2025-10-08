<?php
use App\Events\LogCreated;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

if (! function_exists('activity_log')) {
    function activity_log(
        string $action,
        ?string $description = null,
        string $level = 'info',
        array $meta = []
    ): ActivityLog {
        $log = ActivityLog::create([
            'level' => $level,
            'action' => $action,
            'description' => $description,
            'user_id' => Auth::id(),
            'meta' => $meta,
        ]);

        broadcast(new LogCreated($log))->toOthers();

        return $log;
    }
}
