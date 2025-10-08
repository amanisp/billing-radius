<?php

use App\Events\ActivityLogged;

if (! function_exists('activity_log')) {
    function activity_log(string $operation, ?string $tableName = null, array $details = [], ?string $username = null, ?string $role = null)
    {
        event(new ActivityLogged($operation, $tableName, $details, $username, $role));
    }
}
if (! function_exists('activity_log_with_user')) {
    function activity_log_with_user(string $operation, ?string $tableName = null, array $details = [], ?string $username = null, ?string $role = null)
    {
        activity_log($operation, $tableName, $details, $username, $role);
    }
}
