<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ActivityLogged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param string $operation   The action performed (created, updated, deleted, etc.)
     * @param string|array|null $table_name  The name(s) of the table(s) affected
     * @param array $details      For updates: ['old' => [...], 'new' => [...]]
     * @param string|null $username  Optional: who did the action (default: Auth user)
     * @param string|null $role      Optional: role of user (default: Auth role)
     */
    public function __construct(
        public string $operation,
        public array|string|null $table_name = null,
        public mixed $details = [],
        public ?string $username = null,
        public ?string $role = null
    ) {
        Log::info("activity logged event constructed", [
            'operation' => $operation,
            'table_name' => $table_name,
            'details' => $details,
            'username' => $username,
            'role' => $role,
        ]);
    }
}
