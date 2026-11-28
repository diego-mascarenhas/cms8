<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\MeetingSpeakerLibrary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnrollUserVoiceSample implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(public int $userId)
    {
        $driver = (string) config('queue.default');

        if ($driver === 'database' || $driver === 'redis')
        {
            $this->onConnection($driver.'-meetings');
        }

        $this->onQueue('meetings');
    }

    public function handle(MeetingSpeakerLibrary $library): void
    {
        $user = User::query()->find($this->userId);
        if (! $user instanceof User)
        {
            return;
        }

        $library->enroll($user);
    }
}
