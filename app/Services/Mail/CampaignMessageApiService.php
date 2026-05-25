<?php

namespace App\Services\Mail;

use App\Models\Category;
use App\Models\Message;
use App\Models\MessageDeliveryStat;
use App\Models\Team;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CampaignMessageApiService
{
    public const PER_PAGE = 20;

    public function paginate(Team $team, string $search, int $page, int $perPage): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        $query = $this->baseQuery($team);

        if ($search !== '')
        {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findForTeam(Team $team, int $id): ?Message
    {
        return $this->baseQuery($team)
            ->with(['category', 'contactStatus', 'template', 'deliveries'])
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatForList(Message $message): array
    {
        $total = (int) ($message->deliveries_count ?? 0);
        $sent = (int) ($message->sent_count ?? 0);
        $delivered = (int) ($message->delivered_count ?? 0);
        $opened = (int) ($message->opened_count ?? 0);
        $openRate = $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0.0;

        return [
            'id' => $message->id,
            'name' => $message->name,
            'status' => $this->listStatus($message),
            'contact_categories' => $this->formatContactCategories($message),
            'contact_categories_label' => $message->contactCategoriesLabel(),
            'contact_status' => $message->contactStatus ? [
                'id' => $message->contactStatus->id,
                'name' => $message->contactStatus->name,
            ] : null,
            'progress' => [
                'total' => $total,
                'sent' => $sent,
                'delivered' => $delivered,
                'opened' => $opened,
                'open_rate' => $openRate,
                'has_deliveries' => $total > 0,
            ],
            'scheduled_send_at' => $message->scheduled_send_at?->toIso8601String(),
            'updated_at' => $message->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatForShow(Message $message, Team $team): array
    {
        if (! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }

        $stats = $this->computeAndPersistStats($message);
        $emailConfig = $team->getOutgoingEmailConfig();
        $fromName = trim((string) ($emailConfig['from_name'] ?? ''));
        $fromAddress = trim((string) ($emailConfig['from_address'] ?? ''));
        $senderConfigured = $fromName !== '' && $fromAddress !== '';

        return [
            'id' => $message->id,
            'name' => $message->name,
            'text' => $message->text,
            'status' => $this->listStatus($message),
            'contact_categories' => $this->formatContactCategories($message),
            'contact_categories_label' => $message->contactCategoriesLabel(),
            'contact_status' => $message->contactStatus ? [
                'id' => $message->contactStatus->id,
                'name' => $message->contactStatus->name,
            ] : null,
            'contact_status_label' => $message->contactStatus
                ? $message->contactStatus->name
                : (string) __('app.message_form_contact_status_all'),
            'template' => $message->template ? [
                'id' => $message->template->id,
                'name' => $message->template->name,
            ] : null,
            'sender' => [
                'from_name' => $fromName,
                'from_address' => $fromAddress,
                'configured' => $senderConfigured,
            ],
            'scheduled_send_at' => $message->scheduled_send_at?->toIso8601String(),
            'started_at' => $message->started_at?->toIso8601String(),
            'updated_at' => $message->updated_at?->toIso8601String(),
            'stats' => $stats,
            'stats_updated_at' => now()->toIso8601String(),
            'stats_updated_at_label' => now()->format('H:i:s'),
            'campaign_progress' => $this->campaignProgress($stats),
        ];
    }

    /**
     * Mirrors {@see \App\Livewire\DeliveryStats} real-time stats for API consumers.
     *
     * @return array<string, int|float>
     */
    public function computeAndPersistStats(Message $message): array
    {
        $message->loadMissing('deliveries');
        $deliveries = $message->deliveries;

        $subscribers = $deliveries->pluck('contact_id')->unique()->count();
        if ($subscribers === 0)
        {
            $subscribers = $this->potentialSubscribersCount($message);
        }

        $sent = $deliveries->filter(function ($delivery)
        {
            return $delivery->sent_at && $delivery->sent_at->isPast();
        })->count();

        $delivered = $deliveries->whereNotNull('delivered_at')->count();
        $opened = $deliveries->whereNotNull('opened_at')->count();
        $clicks = $deliveries->whereNotNull('clicked_at')->count();

        $failedDeliveries = $deliveries->where('status_id', 4);
        $failed = $failedDeliveries->filter(function ($failedDelivery) use ($deliveries)
        {
            $hasSuccessfulResend = $deliveries->first(function ($delivery) use ($failedDelivery)
            {
                return $delivery->contact_id === $failedDelivery->contact_id
                    && $delivery->id !== $failedDelivery->id
                    && $delivery->created_at > $failedDelivery->created_at
                    && ($delivery->delivered_at !== null || $delivery->status_id === 1);
            });

            return ! $hasSuccessfulResend;
        })->count();

        $pending = $deliveries->filter(function ($delivery)
        {
            return ! $delivery->sent_at || $delivery->sent_at->isFuture();
        })->count();

        $openRate = $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0.0;

        MessageDeliveryStat::updateOrCreate(
            ['message_id' => $message->id],
            [
                'subscribers' => $subscribers,
                'sent' => $sent,
                'delivered' => $delivered,
                'opened' => $opened,
                'clicks' => $clicks,
                'failed' => $failed,
                'remaining' => $pending,
                'rejected' => 0,
                'unsubscribed' => 0,
                'unique_opens' => $opened,
                'ratio' => $openRate,
            ],
        );

        return [
            'subscribers' => $subscribers,
            'sent' => $sent,
            'delivered' => $delivered,
            'opened' => $opened,
            'clicks' => $clicks,
            'failed' => $failed,
            'remaining' => $pending,
            'open_rate' => $openRate,
        ];
    }

    /**
     * @param  array<string, int|float>  $stats
     * @return array<string, mixed>|null
     */
    private function campaignProgress(array $stats): ?array
    {
        $subscribers = (int) ($stats['subscribers'] ?? 0);
        if ($subscribers <= 0)
        {
            return null;
        }

        $sent = (int) ($stats['sent'] ?? 0);
        $delivered = (int) ($stats['delivered'] ?? 0);
        $opened = (int) ($stats['opened'] ?? 0);
        $failed = (int) ($stats['failed'] ?? 0);

        $sentPercent = min(100, round(($sent / $subscribers) * 100, 1));
        $deliveredPercent = min(100, round(($delivered / $subscribers) * 100, 1));
        $openedPercent = min(100, round(($opened / $subscribers) * 100, 1));
        $failedPercent = min(100, round(($failed / $subscribers) * 100, 1));

        $totalPercent = $sentPercent + $failedPercent;
        if ($totalPercent > 100)
        {
            $sentPercent = 100 - $failedPercent;
        }

        return [
            'open_rate' => (float) ($stats['open_rate'] ?? 0),
            'sent_percent' => $sentPercent,
            'delivered_percent' => $deliveredPercent,
            'opened_percent' => $openedPercent,
            'failed_percent' => $failedPercent,
        ];
    }

    private function potentialSubscribersCount(Message $message): int
    {
        return $this->contactsQueryForMessage($message)->count();
    }

    private function contactsQueryForMessage(Message $message): Builder
    {
        return $message->audienceContactsQuery();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function formatContactCategories(Message $message): array
    {
        $message->loadMissing('contactCategories');

        return $message->contactCategories
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, label: string}
     */
    public function listStatus(Message $message): array
    {
        $raw = (int) ($message->getRawOriginal('status_id') ?? 0);
        $isActive = $raw === 1 || $raw === 2;

        if ($message->scheduled_send_at?->isFuture())
        {
            return [
                'key' => 'scheduled',
                'label' => (string) __('app.message_list_status_scheduled'),
            ];
        }

        if ($isActive)
        {
            return [
                'key' => 'sending',
                'label' => (string) __('app.message_list_status_sending'),
            ];
        }

        return [
            'key' => 'paused',
            'label' => (string) __('app.message_list_status_paused'),
        ];
    }

    private function baseQuery(Team $team): Builder
    {
        return Message::query()
            ->where('team_id', $team->id)
            ->with(['category', 'contactStatus'])
            ->withCount([
                'deliveries',
                'deliveries as sent_count' => function ($query)
                {
                    $query->whereNotNull('sent_at');
                },
                'deliveries as delivered_count' => function ($query)
                {
                    $query->whereNotNull('delivered_at');
                },
                'deliveries as opened_count' => function ($query)
                {
                    $query->whereNotNull('opened_at');
                },
            ]);
    }
}
