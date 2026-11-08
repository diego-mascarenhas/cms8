<?php

namespace App\Jobs;

use App\Models\MeetingTranscript;
use App\Services\MeetingTranscriptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TranscribeMeetingTranscript implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $meetingTranscriptId)
    {
        $driver = (string) config('queue.default');

        if ($driver === 'database' || $driver === 'redis')
        {
            $this->onConnection($driver.'-meetings');
        }

        $this->onQueue('meetings');
    }

    public function handle(MeetingTranscriptService $transcripts): void
    {
        $meeting = MeetingTranscript::withoutGlobalScope('team')->find($this->meetingTranscriptId);
        if (! $meeting instanceof MeetingTranscript)
        {
            return;
        }

        $transcripts->transcribe($meeting);
    }
}
