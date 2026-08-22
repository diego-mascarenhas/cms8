<?php

namespace App\Services\PaidAds;

use App\Enums\AdPlatform;
use App\Enums\PaidAdCampaignStatus;
use App\Enums\PaidAdObjective;
use App\Models\CalendarEvent;
use App\Models\PaidAdCampaign;

class PaidAdCampaignCalendarSyncer
{
    public function sync(PaidAdCampaign $campaign): void
    {
        $campaign->loadMissing(['team', 'platforms']);

        if ($campaign->start_at === null)
        {
            $this->forget($campaign);

            return;
        }

        $team = $campaign->team;
        if ($team === null || ! $team->hasModule('calendar'))
        {
            return;
        }

        $start = $campaign->start_at;
        $end = $campaign->end_at ?? $start->copy()->addDay();
        if ($end->lte($start))
        {
            $end = $start->copy()->addHour();
        }

        $platforms = $campaign->platforms
            ->map(function ($platform): string
            {
                return $platform->platform instanceof AdPlatform
                    ? $platform->platform->label()
                    : (string) $platform->platform;
            })
            ->filter()
            ->implode(', ');

        $payload = [
            'team_id' => $campaign->team_id,
            'title' => 'Ads · '.$campaign->name,
            'start' => $start,
            'end' => $end,
            'all_day' => false,
            'label' => 'Business',
            'location' => $platforms !== '' ? $platforms : null,
            'notes' => $this->notes($campaign, $platforms),
            'url' => $this->campaignUrl($campaign),
        ];

        $event = $campaign->calendar_event_id
            ? CalendarEvent::withoutGlobalScopes()->find($campaign->calendar_event_id)
            : null;

        if ($event)
        {
            $event->fill($payload)->save();

            return;
        }

        $event = CalendarEvent::withoutGlobalScopes()->create($payload);
        $campaign->forceFill(['calendar_event_id' => $event->id])->saveQuietly();
    }

    public function forget(PaidAdCampaign $campaign): void
    {
        if (! $campaign->calendar_event_id)
        {
            return;
        }

        CalendarEvent::withoutGlobalScopes()
            ->whereKey($campaign->calendar_event_id)
            ->delete();

        if ($campaign->exists)
        {
            $campaign->forceFill(['calendar_event_id' => null])->saveQuietly();
        }
    }

    private function notes(PaidAdCampaign $campaign, string $platforms): string
    {
        $status = $campaign->status instanceof PaidAdCampaignStatus
            ? $campaign->status->label()
            : (string) $campaign->status;

        $objective = $campaign->objective instanceof PaidAdObjective
            ? $campaign->objective->label()
            : (string) $campaign->objective;

        $parts = [__('Paid ads campaign'), $status, $objective];
        if ($platforms !== '')
        {
            $parts[] = $platforms;
        }

        return implode(' · ', $parts);
    }

    private function campaignUrl(PaidAdCampaign $campaign): string
    {
        $spaUrl = rtrim((string) config('services.paid_ads.spa_url', ''), '/');
        if ($spaUrl !== '')
        {
            return $spaUrl.'/campaigns/'.$campaign->id;
        }

        return route('paid-ads.show', $campaign->id);
    }
}
