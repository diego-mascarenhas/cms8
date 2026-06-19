<?php

namespace App\Jobs;

use App\Models\ScheduledMessage;
use App\Services\WhatsApp\LocalWhatsAppGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly int $scheduledMessageId) {}

    public function handle(): void
    {
        $scheduled = ScheduledMessage::find($this->scheduledMessageId);

        if (! $scheduled || ! $scheduled->isPending())
        {
            return;
        }

        if ($scheduled->scheduled_at->isFuture())
        {
            self::dispatch($this->scheduledMessageId)->delay($scheduled->scheduled_at);

            return;
        }

        $team = $scheduled->team;

        if (! $team)
        {
            $scheduled->markAsFailed('Team not found');

            return;
        }

        try
        {
            $baseUrl = $team->getWhatsAppServiceBaseUrl();

            if (empty($baseUrl))
            {
                throw new \RuntimeException('WhatsApp service not configured for team');
            }

            $gateway = new LocalWhatsAppGateway(
                $baseUrl,
                config('whatsapp.local.webhook_secret'),
                $team->id,
            );

            $gateway->sendMessage(
                $scheduled->recipient,
                $scheduled->body,
                array_merge($scheduled->metadata ?? [], ['source' => 'scheduled_message', 'scheduled_message_id' => $scheduled->id]),
                $scheduled->scheduled_by_user_id,
            );

            $scheduled->markAsSent();

            Log::info('Scheduled message sent', ['id' => $scheduled->id, 'recipient' => $scheduled->recipient, 'team_id' => $team->id]);
        } catch (\Throwable $e)
        {
            $scheduled->markAsFailed($e->getMessage());

            Log::error('Scheduled message failed', ['id' => $scheduled->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }
}
