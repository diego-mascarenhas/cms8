<?php

namespace App\Jobs;

use App\Models\MeetingTranscript;
use App\Services\MeetingSummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SummarizeMeetingTranscript implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

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

    public function handle(MeetingSummaryService $summaries): void
    {
        $meeting = MeetingTranscript::withoutGlobalScope('team')->find($this->meetingTranscriptId);
        if (! $meeting instanceof MeetingTranscript || is_array($meeting->summary))
        {
            return;
        }

        try
        {
            $summaries->summarize($meeting, createTasks: false);
        } catch (Throwable $exception)
        {
            $meeting->forceFill([
                'summary' => [
                    'error' => mb_substr($exception->getMessage(), 0, 1000),
                ],
            ])->save();
        }
    }
}
