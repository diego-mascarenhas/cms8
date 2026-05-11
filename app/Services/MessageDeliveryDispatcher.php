<?php

namespace App\Services;

use App\Enums\MessageDeliverySendProfile;
use App\Jobs\SendMessageCampaignJob;
use App\Models\MessageDelivery;
use Illuminate\Foundation\Bus\PendingDispatch;

/**
 * Punto único para encolar {@see SendMessageCampaignJob} según perfil Campaña vs Mensaje:
 * distinta cola (priorización en workers) y jitter opcional al encolar.
 */
class MessageDeliveryDispatcher
{
    /**
     * Encola el envío usando cola/jitter derivados del perfil (salvo jitter desactivado).
     */
    public function enqueue(
        MessageDelivery $delivery,
        MessageDeliverySendProfile $profile = MessageDeliverySendProfile::Auto,
        bool $withEnqueueJitter = false,
    ): PendingDispatch {
        $resolved = $this->resolvedProfile($delivery, $profile);
        $segment = $resolved === MessageDeliverySendProfile::Campaign ? 'campaign' : 'message';

        $queue = (string) config("message_delivery_dispatch.{$segment}.queue");
        if ($queue === '')
        {
            $queue = (string) config('message_delivery_dispatch.fallback_queue', 'mailer');
        }

        $pending = SendMessageCampaignJob::dispatch($delivery)->onQueue($queue);

        $connection = config('message_delivery_dispatch.connection');
        if (is_string($connection) && $connection !== '')
        {
            $pending->onConnection($connection);
        }

        if ($withEnqueueJitter)
        {
            $seconds = $this->randomJitterSeconds($segment);
            if ($seconds > 0)
            {
                $pending->delay(now()->addSeconds($seconds));
            }
        }

        return $pending;
    }

    private function resolvedProfile(MessageDelivery $delivery, MessageDeliverySendProfile $profile): MessageDeliverySendProfile
    {
        if ($profile !== MessageDeliverySendProfile::Auto)
        {
            return $profile;
        }

        return $delivery->campaign_id !== null && $delivery->campaign_id !== 0
            ? MessageDeliverySendProfile::Campaign
            : MessageDeliverySendProfile::Message;
    }

    /**
     * @param  non-empty-string  $segment
     */
    private function randomJitterSeconds(string $segment): int
    {
        /** @var array{min?: int, max?: int} $bounds */
        $bounds = config("message_delivery_dispatch.{$segment}.dispatch_jitter_seconds", ['min' => 0, 'max' => 0]);
        $min = max(0, (int) ($bounds['min'] ?? 0));
        $max = max($min, (int) ($bounds['max'] ?? 0));

        if ($max === 0 && $min === 0)
        {
            return 0;
        }

        return rand($min, $max);
    }
}
