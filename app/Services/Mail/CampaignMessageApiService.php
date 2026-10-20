<?php

namespace App\Services\Mail;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryStat;
use App\Models\Team;
use App\Services\MailerCategoryService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

    public function paginateDeliveries(Message $message, string $search, int $page, int $perPage, ?string $status = null): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        $query = MessageDelivery::query()
            ->with(['contact.user', 'contact.list60'])
            ->where('message_id', $message->id);

        if ($search !== '')
        {
            $query->whereHas('contact', function (Builder $contactQuery) use ($search): void
            {
                $contactQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $this->applyDeliveryStatusFilter($query, $status);

        $paginator = $query
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (MessageDelivery $delivery): array => $this->formatDelivery($delivery)),
        );

        return $paginator;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatDelivery(MessageDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'contact_id' => $delivery->contact?->id,
            'contact_name' => $delivery->contact?->name ?: '—',
            'contact_email' => $delivery->contact?->email ?: '—',
            'contact_photo_url' => $delivery->contact?->storedPhotoUrl(),
            'scheduled_for' => $delivery->scheduled_for?->toIso8601String(),
            'sent_at' => $delivery->sent_at?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'opened_at' => $delivery->opened_at?->toIso8601String(),
            'clicked_at' => $delivery->clicked_at?->toIso8601String(),
            'status_key' => $this->deliveryStatusKey($delivery),
            'status_text' => $this->deliveryStatusText($delivery),
            'has_opened' => $delivery->opened_at !== null,
            'has_clicked' => $delivery->clicked_at !== null,
            'failed_at' => $delivery->failureRecordedAt()?->toIso8601String(),
            'unsubscribed_at' => $delivery->contact?->unsubscribedAt()?->toIso8601String(),
            'in_list60' => $delivery->contact?->list60 !== null,
            'email_valid' => $delivery->contact?->storedChannelValid('email'),
            'email_last_error' => $this->deliveryFailureText($delivery),
        ];
    }

    /**
     * Same buckets as {@see self::computeAndPersistStats()}, so a stat click lists those rows.
     */
    private function applyDeliveryStatusFilter(Builder $query, ?string $status): void
    {
        if ($status === null || $status === '')
        {
            return;
        }

        if ($status === 'sent')
        {
            $query->whereNotNull('sent_at')->where('sent_at', '<=', now());

            return;
        }

        if ($status === 'delivered')
        {
            $query->whereNotNull('delivered_at');

            return;
        }

        if ($status === 'opened')
        {
            $query->whereNotNull('opened_at');

            return;
        }

        if ($status === 'clicked')
        {
            $query->whereNotNull('clicked_at');

            return;
        }

        if ($status === 'unsubscribed')
        {
            $query->whereHas('contact', function (Builder $contact): void
            {
                $contact->where('status_id', 4);
            });

            return;
        }

        if ($status === 'temporary')
        {
            $this->whereStillFailed($query);
            $this->whereTemporaryFailure($query);

            return;
        }

        if ($status === 'failed')
        {
            $this->whereStillFailed($query);
            $this->whereTemporaryFailure($query, false);
        }
    }

    private function whereStillFailed(Builder $query): void
    {
        $query->where('message_deliveries.status_id', 4)
            ->whereNotExists(function ($later): void
            {
                $later->selectRaw('1')
                    ->from('message_deliveries as later_deliveries')
                    ->whereColumn('later_deliveries.message_id', 'message_deliveries.message_id')
                    ->whereColumn('later_deliveries.contact_id', 'message_deliveries.contact_id')
                    ->whereColumn('later_deliveries.id', '!=', 'message_deliveries.id')
                    ->whereColumn('later_deliveries.created_at', '>', 'message_deliveries.created_at')
                    ->where(function ($success): void
                    {
                        $success->whereNotNull('later_deliveries.delivered_at')
                            ->orWhere('later_deliveries.status_id', 1);
                    });
            });
    }

    private function whereTemporaryFailure(Builder $query, bool $include = true): void
    {
        $sql = $this->temporaryFailureSql();
        $query->whereRaw($include ? '('.$sql.')' : 'NOT ('.$sql.')');
    }

    private function temporaryFailureSql(): string
    {
        $likes = [];
        foreach (MessageDelivery::temporaryFailureNeedles() as $needle)
        {
            $safe = str_replace("'", "''", $needle);
            $likes[] = "LOWER(COALESCE(message_deliveries.error_message, '')) LIKE '%{$safe}%'";
            $likes[] = "LOWER(COALESCE(message_deliveries.bounce_reason, '')) LIKE '%{$safe}%'";
        }

        $likeSql = implode(' OR ', $likes);

        return "COALESCE(message_deliveries.bounce_type, '') = 'soft'"
            ." OR (COALESCE(message_deliveries.bounce_type, '') NOT IN ('hard', 'complaint', 'block') AND ({$likeSql}))";
    }

    private function deliveryFailureText(MessageDelivery $delivery): ?string
    {
        $key = $this->deliveryStatusKey($delivery);
        if ($key !== 'failed' && $key !== 'temporary')
        {
            return null;
        }

        $message = trim((string) $delivery->error_message);
        if ($message !== '')
        {
            return $message;
        }

        return $delivery->contact?->storedChannelLastError('email');
    }

    private function deliveryStatusKey(MessageDelivery $delivery): string
    {
        if ((int) $delivery->status_id === 4)
        {
            return $delivery->isTemporaryFailure() ? 'temporary' : 'failed';
        }
        if ($delivery->delivered_at)
        {
            return 'delivered';
        }
        if ((int) $delivery->status_id === 3 && $delivery->clicked_at === null && $delivery->sent_at === null)
        {
            return 'sending';
        }
        if ($delivery->sent_at?->isFuture())
        {
            return 'scheduled';
        }
        if ($delivery->sent_at)
        {
            return 'sent';
        }

        return 'pending';
    }

    private function deliveryStatusText(MessageDelivery $delivery): string
    {
        return match ($this->deliveryStatusKey($delivery))
        {
            'temporary' => 'Error',
            'failed' => 'Fallido',
            'delivered' => 'Entregado',
            'sending' => 'Enviando',
            'scheduled' => 'Programado',
            'sent' => 'Enviado',
            default => 'Pendiente',
        };
    }

    public function findForTeam(Team $team, int $id): ?Message
    {
        return $this->baseQuery($team)
            ->with(['category', 'contactStatus', 'contactCountry', 'template', 'deliveries'])
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatForList(Message $message, Team $team): array
    {
        $total = (int) ($message->deliveries_count ?? 0);
        $sent = (int) ($message->sent_count ?? 0);
        $delivered = (int) ($message->delivered_count ?? 0);
        $opened = (int) ($message->opened_count ?? 0);
        $openRate = $this->openRate($opened, $delivered, $sent);
        $team->loadMissing('settings');

        return [
            'id' => $message->id,
            'name' => $message->name,
            'sender_name' => $message->resolvedMailerSender($team)['from_name'],
            'status' => $this->listStatus($message),
            'contact_categories' => $this->formatContactCategories($message),
            'contact_categories_label' => $message->contactCategoriesLabel(),
            'contact_status' => $message->contactStatus ? [
                'id' => $message->contactStatus->id,
                'name' => $message->contactStatus->name,
            ] : null,
            'contact_country' => $this->formatContactCountry($message),
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
        $resolvedSender = $message->resolvedMailerSender($team);
        $fromName = $resolvedSender['from_name'];
        $fromAddress = $resolvedSender['from_address'];
        $senderConfigured = $fromName !== '' && $fromAddress !== '';
        $overrideName = trim((string) $message->from_name);
        $overrideAddress = trim((string) $message->from_address);

        $status = $this->listStatus($message);

        return [
            'id' => $message->id,
            'name' => $message->name,
            'text' => $message->text,
            'type_id' => (int) $message->type_id,
            'mail_html' => $message->mail_html,
            'template_id' => $message->template_id,
            'show_unsubscribe' => (bool) $message->show_unsubscribe,
            'enable_open_tracking' => (bool) $message->enable_open_tracking,
            'enable_click_tracking' => (bool) $message->enable_click_tracking,
            'list60_enabled' => $team->hasModule('list60'),
            'min_hours_between_emails' => (int) ($message->min_hours_between_emails ?? 0),
            'send_allowed_weekdays' => $message->send_allowed_weekdays,
            'send_window_start' => $message->send_window_start,
            'send_window_end' => $message->send_window_end,
            'status' => $status,
            'pause_reason' => $status['key'] === 'paused' ? ($message->pause_reason ?: null) : null,
            'contact_categories' => $this->formatContactCategories($message),
            'contact_categories_label' => $message->contactCategoriesLabel(),
            'contact_status' => $message->contactStatus ? [
                'id' => $message->contactStatus->id,
                'name' => $message->contactStatus->name,
                'label_class' => (string) ($message->contactStatus->label_class ?? ''),
            ] : null,
            'contact_status_label' => $message->contactStatus
                ? $message->contactStatus->name
                : (string) __('app.message_form_contact_status_all'),
            'contact_country' => $this->formatContactCountry($message),
            'template' => $message->template ? [
                'id' => $message->template->id,
                'name' => $message->template->name,
            ] : null,
            'from_name' => $overrideName !== '' ? $overrideName : null,
            'from_address' => $overrideAddress !== '' ? $overrideAddress : null,
            'custom_sender_allowed' => $team->allowsCustomMessageSender(),
            'send_pace' => $message->currentSendPaceText() ?? $team->mailerSendPaceText(),
            'conversion_endpoint' => \App\Helpers\EmailTrackingHelper::conversionEndpoint(),
            'sender' => [
                'from_name' => $fromName,
                'from_address' => $fromAddress,
                'configured' => $senderConfigured,
            ],
            'has_deliveries' => $message->relationLoaded('deliveries')
                ? $message->deliveries->isNotEmpty()
                : MessageDelivery::query()->where('message_id', $message->id)->exists(),
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

        $stillFailed = $deliveries->where('status_id', 4)->filter(function ($failedDelivery) use ($deliveries)
        {
            $hasSuccessfulResend = $deliveries->first(function ($delivery) use ($failedDelivery)
            {
                return $delivery->contact_id === $failedDelivery->contact_id
                    && $delivery->id !== $failedDelivery->id
                    && $delivery->created_at > $failedDelivery->created_at
                    && ($delivery->delivered_at !== null || $delivery->status_id === 1);
            });

            return ! $hasSuccessfulResend;
        });
        $temporary = $stillFailed->filter(fn (MessageDelivery $delivery): bool => $delivery->isTemporaryFailure())->count();
        $failed = $stillFailed->count() - $temporary;

        $pending = $deliveries->filter(function ($delivery)
        {
            return ! $delivery->sent_at || $delivery->sent_at->isFuture();
        })->count();

        $contactIds = $deliveries->pluck('contact_id')->filter()->unique()->values();
        $unsubscribed = $contactIds->isEmpty()
            ? 0
            : Contact::withoutGlobalScopes()
                ->whereIn('id', $contactIds)
                ->where('status_id', 4)
                ->count();

        $openRate = $this->openRate($opened, $delivered, $sent);

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
                'unsubscribed' => $unsubscribed,
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
            'temporary' => $temporary,
            'remaining' => $pending,
            'unsubscribed' => $unsubscribed,
            'open_rate' => $openRate,
        ];
    }

    /**
     * Opens divided by deliveries. When opens outnumber recorded deliveries, use sends so the rate stays within the stats column.
     */
    private function openRate(int $opened, int $delivered, int $sent): float
    {
        if ($opened < 1)
        {
            return 0.0;
        }

        if ($delivered > 0)
        {
            $rate = round(($opened / $delivered) * 100, 2);
            if ($rate <= 100)
            {
                return $rate;
            }
        }

        $base = max($sent, $delivered);
        if ($base < 1)
        {
            return 100.0;
        }

        return min(100, round(($opened / $base) * 100, 2));
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
     * @return list<array{id: int, name: string, color: string|null}>
     */
    private function formatContactCategories(Message $message): array
    {
        $message->loadMissing('contactCategories');

        return $message->contactCategories
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => MailerCategoryService::normalizeColor($category->color),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, code: string}|null
     */
    private function formatContactCountry(Message $message): ?array
    {
        $message->loadMissing('contactCountry');
        $country = $message->contactCountry;
        if ($country === null)
        {
            return null;
        }

        return [
            'id' => (int) $country->id,
            'name' => (string) $country->name,
            'code' => strtolower((string) $country->code),
        ];
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
            ->with(['category', 'contactStatus', 'contactCountry'])
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

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Team $team, int $days): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $until = now();
        $since = $until->copy()->subDays($days - 1)->startOfDay();

        return [
            'period_days' => $days,
            'totals' => $this->dashboardTotals($team, $since, $until),
            'activity' => $this->dashboardActivity($team->id, $since, $until, $days > 31),
            'live' => $this->dashboardLive($team),
            'attention' => $this->dashboardAttention($team),
            'opens_by_country' => $this->dashboardOpensByCountry($team->id, $since, $until),
            'campaigns' => $this->dashboardCampaigns($team),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function dashboardTotals(Team $team, Carbon $since, Carbon $until): array
    {
        $row = MessageDelivery::query()
            ->where('team_id', $team->id)
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN sent_at >= ? AND sent_at <= ? THEN contact_id END) as subscribers,
                 SUM(CASE WHEN sent_at >= ? AND sent_at <= ? THEN 1 ELSE 0 END) as sent,
                 SUM(CASE WHEN delivered_at >= ? AND delivered_at <= ? THEN 1 ELSE 0 END) as delivered,
                 SUM(CASE WHEN opened_at >= ? AND opened_at <= ? THEN 1 ELSE 0 END) as opened,
                 SUM(CASE WHEN clicked_at >= ? AND clicked_at <= ? THEN 1 ELSE 0 END) as clicks,
                 COUNT(DISTINCT CASE WHEN sent_at >= ? AND sent_at <= ? THEN message_id END) as campaigns',
                [$since, $until, $since, $until, $since, $until, $since, $until, $since, $until, $since, $until],
            )
            ->first();

        $failedBase = MessageDelivery::query()
            ->where('team_id', $team->id)
            ->where('status_id', 4)
            ->where(function ($query) use ($since, $until): void
            {
                $query->whereBetween('bounced_at', [$since, $until])
                    ->orWhere(function ($inner) use ($since, $until): void
                    {
                        $inner->whereNull('bounced_at')->whereBetween('updated_at', [$since, $until]);
                    });
            });

        $temporary = (clone $failedBase)->whereRaw('('.$this->temporaryFailureSql().')')->count();
        $failed = (clone $failedBase)->whereRaw('NOT ('.$this->temporaryFailureSql().')')->count();

        $unsubscribed = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('status_id', 4)
            ->whereExists(function ($query) use ($team, $since, $until): void
            {
                $query->selectRaw('1')
                    ->from('message_deliveries')
                    ->whereColumn('message_deliveries.contact_id', 'contacts.id')
                    ->where('message_deliveries.team_id', $team->id)
                    ->whereBetween('message_deliveries.sent_at', [$since, $until]);
            })
            ->count();

        $sending = 0;
        $paused = 0;
        $scheduled = 0;
        $messages = Message::query()
            ->where('team_id', $team->id)
            ->where(function ($query): void
            {
                $query->where('status_id', 1)
                    ->orWhereNotNull('scheduled_send_at')
                    ->orWhereNotNull('pause_reason');
            })
            ->get(['id', 'status_id', 'scheduled_send_at', 'pause_reason']);

        foreach ($messages as $message)
        {
            $key = $this->listStatus($message)['key'];
            if ($key === 'sending')
            {
                $sending++;
            } elseif ($key === 'scheduled')
            {
                $scheduled++;
            } elseif ($key === 'paused' && filled($message->pause_reason))
            {
                $paused++;
            }
        }

        return [
            'subscribers' => (int) ($row->subscribers ?? 0),
            'sent' => (int) ($row->sent ?? 0),
            'delivered' => (int) ($row->delivered ?? 0),
            'opened' => (int) ($row->opened ?? 0),
            'clicks' => (int) ($row->clicks ?? 0),
            'temporary' => $temporary,
            'failed' => $failed,
            'unsubscribed' => $unsubscribed,
            'campaigns' => (int) ($row->campaigns ?? 0),
            'sending' => $sending,
            'paused' => $paused,
            'scheduled' => $scheduled,
        ];
    }

    /**
     * @return list<array{date: string, sent: int, opened: int, clicks: int}>
     */
    private function dashboardActivity(int $teamId, Carbon $since, Carbon $until, bool $weekly): array
    {
        $sent = $this->countsByDay($teamId, 'sent_at', $since, $until);
        $opened = $this->countsByDay($teamId, 'opened_at', $since, $until);
        $clicks = $this->countsByDay($teamId, 'clicked_at', $since, $until);
        $days = [];
        $cursor = $since->copy()->startOfDay();
        $end = $until->copy()->startOfDay();

        while ($cursor->lte($end))
        {
            $key = $cursor->toDateString();
            $days[] = [
                'date' => $key,
                'sent' => (int) ($sent[$key] ?? 0),
                'opened' => (int) ($opened[$key] ?? 0),
                'clicks' => (int) ($clicks[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        if (! $weekly)
        {
            return $days;
        }

        $weeks = [];
        foreach ($days as $day)
        {
            $week = Carbon::parse($day['date'])->startOfWeek()->toDateString();
            if (! isset($weeks[$week]))
            {
                $weeks[$week] = ['date' => $week, 'sent' => 0, 'opened' => 0, 'clicks' => 0];
            }
            $weeks[$week]['sent'] += $day['sent'];
            $weeks[$week]['opened'] += $day['opened'];
            $weeks[$week]['clicks'] += $day['clicks'];
        }

        return array_values($weeks);
    }

    /**
     * @return array<string, int>
     */
    private function countsByDay(int $teamId, string $column, Carbon $since, Carbon $until): array
    {
        $expression = $this->sqlDay($column);
        $rows = MessageDelivery::query()
            ->where('team_id', $teamId)
            ->whereNotNull($column)
            ->whereBetween($column, [$since, $until])
            ->selectRaw($expression.' as day, COUNT(*) as total')
            ->groupByRaw($expression)
            ->get();

        $counts = [];
        foreach ($rows as $row)
        {
            $counts[substr((string) $row->day, 0, 10)] = (int) $row->total;
        }

        return $counts;
    }

    private function sqlDay(string $column): string
    {
        if (! in_array($column, ['sent_at', 'opened_at', 'clicked_at'], true))
        {
            throw new \InvalidArgumentException('Unsupported day column.');
        }

        if (DB::connection()->getDriverName() === 'pgsql')
        {
            return "to_char({$column}, 'YYYY-MM-DD')";
        }

        return "strftime('%Y-%m-%d', {$column})";
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dashboardLive(Team $team): array
    {
        return Message::query()
            ->where('team_id', $team->id)
            ->where('status_id', 1)
            ->where(function ($query): void
            {
                $query->whereNull('scheduled_send_at')->orWhere('scheduled_send_at', '<=', now());
            })
            ->with('contactCategories')
            ->withCount($this->dashboardDeliveryCounts())
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get()
            ->map(function (Message $message): array
            {
                $subscribers = (int) $message->deliveries_count;
                $delivered = (int) $message->delivered_count;
                $opened = (int) $message->opened_count;
                $clicks = (int) $message->clicks_count;
                $stats = [
                    'subscribers' => $subscribers,
                    'sent' => (int) $message->sent_count,
                    'delivered' => $delivered,
                    'opened' => $opened,
                    'failed' => (int) $message->failed_count,
                    'open_rate' => $this->openRate($opened, $delivered, (int) $message->sent_count),
                ];
                $progress = $this->campaignProgress($stats);
                if ($progress !== null && $subscribers > 0)
                {
                    $progress['clicks_percent'] = min(100, round(($clicks / $subscribers) * 100, 1));
                }

                return [
                    'id' => $message->id,
                    'name' => $message->name,
                    'audience' => $message->hasContactCategoryFilter() ? $message->contactCategoriesLabel() : null,
                    'subscribers' => $subscribers,
                    'progress' => $progress,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dashboardAttention(Team $team): array
    {
        return Message::query()
            ->where('team_id', $team->id)
            ->where('status_id', 0)
            ->whereIn('pause_reason', [Message::PAUSE_REASON_SPAM, Message::PAUSE_REASON_ERRORS])
            ->orderByRaw('case when pause_reason = ? then 0 else 1 end', [Message::PAUSE_REASON_SPAM])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function (Message $message): array
            {
                $spam = $message->pause_reason === Message::PAUSE_REASON_SPAM;

                return [
                    'kind' => $spam ? 'spam' : 'errors',
                    'message_id' => $message->id,
                    'title' => $spam ? 'Pausada por spam' : 'Pausada por errores',
                    'detail' => $message->name,
                    'at' => $message->updated_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{country_id: int|null, name: string, code: string|null, opened: int}>
     */
    private function dashboardOpensByCountry(int $teamId, Carbon $since, Carbon $until): array
    {
        return MessageDelivery::query()
            ->where('message_deliveries.team_id', $teamId)
            ->whereNotNull('message_deliveries.opened_at')
            ->whereBetween('message_deliveries.opened_at', [$since, $until])
            ->join('contacts', 'contacts.id', '=', 'message_deliveries.contact_id')
            ->whereNull('contacts.deleted_at')
            ->leftJoin('countries', 'countries.id', '=', 'contacts.country')
            ->groupBy('countries.id', 'countries.name', 'countries.code')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('countries.name')
            ->selectRaw('countries.id as country_id, countries.name as country_name, countries.code as country_code, COUNT(*) as opened')
            ->limit(8)
            ->get()
            ->map(function ($row): array
            {
                $name = trim((string) ($row->country_name ?? ''));

                return [
                    'country_id' => $row->country_id !== null ? (int) $row->country_id : null,
                    'name' => $name !== '' ? $name : 'Sin país',
                    'code' => $row->country_code !== null ? strtolower((string) $row->country_code) : null,
                    'opened' => (int) $row->opened,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dashboardCampaigns(Team $team): array
    {
        return Message::query()
            ->where('team_id', $team->id)
            ->withMax('deliveries', 'sent_at')
            ->withCount($this->dashboardDeliveryCounts())
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(function (Message $message): array
            {
                $scheduled = $message->scheduled_send_at?->isFuture() === true;
                $lastSent = $message->getAttribute('deliveries_max_sent_at');

                return [
                    'id' => $message->id,
                    'name' => $message->name,
                    'status' => $this->listStatus($message),
                    'subscribers' => (int) $message->deliveries_count,
                    'sent' => (int) $message->sent_count,
                    'delivered' => (int) $message->delivered_count,
                    'opened' => (int) $message->opened_count,
                    'clicks' => (int) $message->clicks_count,
                    'temporary' => (int) $message->temporary_count,
                    'failed' => (int) $message->failed_count,
                    'unsubscribed' => (int) $message->unsubscribed_count,
                    'activity_at' => $scheduled
                        ? $message->scheduled_send_at?->toIso8601String()
                        : ($lastSent ? Carbon::parse($lastSent)->toIso8601String() : $message->updated_at?->toIso8601String()),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardDeliveryCounts(): array
    {
        return [
            'deliveries',
            'deliveries as sent_count' => function ($query): void
            {
                $query->whereNotNull('sent_at')->where('sent_at', '<=', now());
            },
            'deliveries as delivered_count' => function ($query): void
            {
                $query->whereNotNull('delivered_at');
            },
            'deliveries as opened_count' => function ($query): void
            {
                $query->whereNotNull('opened_at');
            },
            'deliveries as clicks_count' => function ($query): void
            {
                $query->whereNotNull('clicked_at');
            },
            'deliveries as temporary_count' => function ($query): void
            {
                $query->where('message_deliveries.status_id', 4)
                    ->whereRaw('('.$this->temporaryFailureSql().')');
            },
            'deliveries as failed_count' => function ($query): void
            {
                $query->where('message_deliveries.status_id', 4)
                    ->whereRaw('NOT ('.$this->temporaryFailureSql().')');
            },
            'deliveries as unsubscribed_count' => function ($query): void
            {
                $query->whereHas('contact', function ($contact): void
                {
                    $contact->withoutGlobalScopes()->where('contacts.status_id', 4);
                });
            },
        ];
    }
}
