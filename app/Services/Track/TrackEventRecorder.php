<?php

namespace App\Services\Track;

use App\Models\Communication;
use App\Models\Contact;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryLink;
use App\Models\MessageDeliveryTracking;
use App\Services\Mail\NewsContactStatus;
use Illuminate\Support\Facades\Log;

class TrackEventRecorder
{
    public function __construct(private NewsContactStatus $newsStatus) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(string $type, array $payload): void
    {
        match ($type)
        {
            'mailer.open' => $this->mailerOpen($payload),
            'mailer.click' => $this->mailerClick($payload),
            'mailer.conversion' => $this->mailerConversion($payload),
            'communication.open' => $this->communicationOpen($payload),
            'communication.click' => $this->communicationClick($payload),
            'unsubscribe' => $this->unsubscribe($payload),
            default => Log::warning('Track event ignored', ['type' => $type]),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mailerOpen(array $payload): void
    {
        $delivery = MessageDelivery::findByTrackingToken((string) ($payload['token'] ?? ''));
        if (! $delivery)
        {
            return;
        }

        $this->storeMailerEvent($delivery, 'opened', [
            'source' => 'email_tracking_pixel_direct',
            'timestamp' => now(),
        ], $payload);

        if (! $delivery->opened_at)
        {
            $delivery->forceFill(['opened_at' => now()])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mailerClick(array $payload): void
    {
        $delivery = MessageDelivery::findByTrackingToken((string) ($payload['token'] ?? ''));
        if (! $delivery)
        {
            return;
        }

        $url = (string) ($payload['url'] ?? '/');

        if (! $delivery->opened_at && $delivery->message && $delivery->message->allowsOpenTracking())
        {
            $this->storeMailerEvent($delivery, 'opened', [
                'source' => 'inferred_from_click',
                'timestamp' => now(),
            ], $payload);
            $delivery->markAsOpened();
        }

        $this->storeMailerEvent($delivery, 'clicked', [
            'source' => 'email_link_click',
            'original_url' => $url,
            'timestamp' => now(),
        ], $payload);

        $this->updateLinkClickCount($delivery, $url);

        if ($delivery->contact && ! str_contains($url, '/unsubscribe/'))
        {
            $this->newsStatus->apply($delivery->contact, NewsContactStatus::CLICK);
        }

        $delivery->markAsClicked();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mailerConversion(array $payload): void
    {
        $delivery = MessageDelivery::findByTrackingToken((string) ($payload['token'] ?? ''));
        if ($delivery?->contact)
        {
            $this->newsStatus->apply($delivery->contact, NewsContactStatus::CONVERSION);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function communicationOpen(array $payload): void
    {
        $communication = Communication::findByTrackingToken((string) ($payload['token'] ?? ''));
        $communication?->markOpened();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function communicationClick(array $payload): void
    {
        $communication = Communication::query()->withoutGlobalScopes()->find((int) ($payload['communication_id'] ?? 0));
        if (! $communication)
        {
            return;
        }

        $communication->markClicked((string) ($payload['url'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function unsubscribe(array $payload): void
    {
        $email = trim((string) ($payload['email'] ?? ''));
        if ($email === '')
        {
            return;
        }

        $contact = Contact::with(['user.roles', 'user.teams', 'user.currentTeam.settings'])
            ->where('email', $email)
            ->first();

        if ($contact)
        {
            $this->newsStatus->apply($contact, NewsContactStatus::UNSUBSCRIBE);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $payload
     */
    private function storeMailerEvent(MessageDelivery $delivery, string $event, array $metadata, array $payload): void
    {
        MessageDeliveryTracking::query()->create([
            'message_delivery_id' => $delivery->id,
            'event' => $event,
            'tracked_at' => now(),
            'ip_address' => $payload['ip'] ?? null,
            'user_agent' => $payload['user_agent'] ?? null,
            'metadata' => $metadata,
        ]);
    }

    private function updateLinkClickCount(MessageDelivery $delivery, string $url): void
    {
        $link = MessageDeliveryLink::query()
            ->where('message_delivery_id', $delivery->id)
            ->where('link', $url)
            ->first();

        if ($link)
        {
            $link->increment('click_count');
            $link->touch();

            return;
        }

        MessageDeliveryLink::query()->create([
            'message_delivery_id' => $delivery->id,
            'link' => $url,
            'click_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
