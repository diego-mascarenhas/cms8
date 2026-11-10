<?php

namespace App\Console\Commands;

use App\Jobs\SendMessageCampaignJob;
use App\Models\Message;
use App\Models\MessageDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProbeSpamPausedCampaigns extends Command
{
    protected $signature = 'campaigns:probe-spam-pause';

    protected $description = 'Send one email per hour from each campaign paused because the provider marked it as spam';

    public function handle(): int
    {
        $interval = max(60, (int) config('services.email.processing.spam_probe_seconds', 3600));
        $messages = Message::withoutGlobalScope('team')
            ->where('status_id', 0)
            ->where('pause_reason', Message::PAUSE_REASON_SPAM)
            ->orderBy('id')
            ->get();

        $queued = 0;
        foreach ($messages as $message)
        {
            if ($this->queueProbe($message, $interval))
            {
                $queued++;
            }
        }

        $this->info("Queued {$queued} spam probes.");

        return 0;
    }

    private function queueProbe(Message $message, int $interval): bool
    {
        $cutoff = now()->subSeconds($interval);
        $recentAttempt = MessageDelivery::query()
            ->where('message_id', $message->id)
            ->whereNull('campaign_id')
            ->where('sent_at', '>=', $cutoff)
            ->exists();
        $cacheKey = 'mailer-spam-probe:'.$message->id;
        if ($recentAttempt || Cache::has($cacheKey))
        {
            return false;
        }

        $delivery = MessageDelivery::query()
            ->where('message_id', $message->id)
            ->whereNull('campaign_id')
            ->whereNull('sent_at')
            ->where('status_id', 1)
            ->whereHas('contact', function ($query): void
            {
                $query->withMailableEmail();
            })
            ->orderBy('scheduled_for')
            ->orderBy('id')
            ->first();
        if (! $delivery)
        {
            return false;
        }

        $data = $delivery->provider_data ?? [];
        $data['spam_probe'] = true;
        $delivery->provider_data = $data;
        $delivery->scheduled_for = now();
        $delivery->save();

        $queue = config('services.email.processing.spam_probe_queue', 'track');
        $pending = SendMessageCampaignJob::dispatch($delivery, false, true);
        if (is_string($queue) && $queue !== '')
        {
            $pending->onQueue($queue);
        }

        Cache::put($cacheKey, $delivery->id, now()->addSeconds($interval));
        $this->info("Queued spam probe {$delivery->id} for message {$message->id}.");

        return true;
    }
}
