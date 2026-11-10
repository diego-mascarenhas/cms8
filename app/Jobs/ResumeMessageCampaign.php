<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

class ResumeMessageCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 110;

    public function __construct(
        public int $messageId,
        public int $readySkip = 0,
        public int $waitingSkip = 0,
        public ?string $scheduleOrigin = null,
    ) {
        $queue = config('message_delivery_dispatch.campaign.queue');
        if (is_string($queue) && $queue !== '')
        {
            $this->onQueue($queue);
        }
    }

    public function handle(): void
    {
        $message = Message::withoutGlobalScopes()->find($this->messageId);
        if (! $message || ! $message->status_id)
        {
            return;
        }

        if ($this->readySkip === 0 && $this->waitingSkip === 0)
        {
            Artisan::call('campaigns:process-active', [
                '--message' => $this->messageId,
                '--skip-reflow' => true,
            ]);

            $message = Message::withoutGlobalScopes()->find($this->messageId);
            if (! $message || ! $message->status_id)
            {
                return;
            }
        }

        $origin = $this->scheduleOrigin !== null
            ? Carbon::parse($this->scheduleOrigin)
            : now();
        $limit = max(1, (int) config('services.email.processing.resume_schedule_chunk', 100));
        $next = $message->reschedulePendingSlice($this->readySkip, $this->waitingSkip, $limit, $origin);
        if ($next === null)
        {
            return;
        }

        $stillActive = Message::withoutGlobalScopes()
            ->whereKey($this->messageId)
            ->where('status_id', 1)
            ->exists();
        if (! $stillActive)
        {
            return;
        }

        self::dispatch(
            $this->messageId,
            $next['ready'],
            $next['waiting'],
            $origin->toIso8601String(),
        );
    }
}
