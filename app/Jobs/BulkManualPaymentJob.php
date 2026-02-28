<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BulkManualPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $listId;
    protected $paymentMethod;
    protected $month;
    protected $userId;

    public function __construct($listId, $paymentMethod, $month, $userId)
    {
        $this->listId = $listId;
        $this->paymentMethod = $paymentMethod;
        $this->month = $month;
        $this->userId = $userId;
    }

    public function handle()
    {
        $user = User::find($this->userId);

        foreach ($this->listId as $memberId) {

            ManualPaymentJob::dispatch(
                $memberId,
                $this->paymentMethod,
                $this->month,
                $user->id
            );
        }
    }
}
