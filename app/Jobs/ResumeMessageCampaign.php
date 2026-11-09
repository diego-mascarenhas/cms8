<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class ResumeMessageCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 110;

    public function __construct(public int $messageId)
    {
        $queue = config('message_delivery_dispatch.campaign.queue');
        if (is_string($queue) && $queue !== '')
        {
            $this->onQueue($queue);
        }
    }

    public function handle(): void
    {
        Artisan::call('campaigns:process-active', [
            '--message' => $this->messageId,
        ]);

        $message = Message::withoutGlobalScopes()->find($this->messageId);
        if (! $message || ! $message->status_id)
        {
            return;
        }

        $message->reschedulePendingDeliveries();
    }
}
