<?php

namespace App\Events;

use App\Models\ActivityLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class LogCreated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(public ActivityLog $log) {}

    public function broadcastOn(): array
    {
        return [new Channel('activity-log')]; // Public channel; use PrivateChannel for restricted access
    }

    public function broadcastAs(): string
    {
        return 'log.created';
    }
}
