<?php

namespace App\Jobs;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ImportMemberRow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    protected $group_id;

    public function __construct(array $data, $group_id)
    {
        $this->data = $data;
        $this->group_id = $group_id;
    }

    public function handle()
    {
        $validator = Validator::make($this->data, [
            'name' => [
                'required',
                Rule::unique('members')->where(fn($query) =>
                $query->where('group_id', $this->group_id))
            ],
            'phone_number' => ['nullable', 'string', 'min:9'],
            'email' => 'nullable|email',
            'nik' => 'nullable|string|digits:16',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) return;

        Member::create(array_merge($this->data, ['group_id' => $this->group_id]));
    }
}
