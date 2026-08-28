<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Conversation;
use App\Models\List60;
use App\Models\Team;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppProfilePhotoStore;
use App\Services\WhatsApp\WhatsAppThreadCategoryService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AssistantCommercialStatsService
{
    public const PERIOD_DAYS = 30;

    private const PIPELINE_STATUS_NAMES = [
        'Lead',
        'En seguimiento',
        'Conversión',
        'Cliente',
        'Perdido',
    ];

    private const CONVERSION_STATUS_NAMES = [
        'Conversión',
        'Cliente',
    ];

    private const SENTIMENT_META = [
        1 => ['label' => 'Muy negativo', 'emoji' => '😡'],
        2 => ['label' => 'Negativo', 'emoji' => '🙁'],
        3 => ['label' => 'Neutral', 'emoji' => '😐'],
        4 => ['label' => 'Positivo', 'emoji' => '🙂'],
        5 => ['label' => 'Muy positivo', 'emoji' => '🥳'],
    ];

    public function __construct(
        private readonly DailyTeamDigestMetricsCollector $digestCollector,
        private readonly ContactDailySentimentService $sentimentService,
        private readonly AssistantPromptCatalog $promptCatalog,
        private readonly TeamSiteAssistantPromptService $siteAssistant,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forTeam(Team $team): array
    {
        $since = now()->subDays(self::PERIOD_DAYS)->startOfDay();
        $contacts = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->with(['status:id,name', 'currentSentiment.sentiment', 'currentSentiment.intent', 'categories:id,name,color', 'user:id,profile_photo_path'])
            ->get(['id', 'name', 'surname', 'phone', 'email', 'status_id', 'data', 'created_at', 'responsible_id', 'user_id']);

        $pipeline = $this->pipeline($contacts);
        $whatsapp = $this->whatsappMetrics($team, $contacts, $since);

        return [
            'period_days' => self::PERIOD_DAYS,
            'generated_at' => now()->toIso8601String(),
            'pipeline' => $pipeline,
            'kpis' => [
                'leads' => $this->pipelineCount($pipeline, 'Lead'),
                'follow_up' => $this->pipelineCount($pipeline, 'En seguimiento'),
                'conversions' => $this->pipelineCount($pipeline, 'Conversión'),
                'clients' => $this->pipelineCount($pipeline, 'Cliente'),
                'unread_inbound' => $whatsapp['unread_inbound'],
                'waiting_replies' => count($whatsapp['inbox_waiting']),
                'list60_due' => $this->list60DueCount($team),
                'appointments_today' => $this->appointmentsToday($team),
            ],
            'leads_trend' => $this->leadsTrend($contacts, $since),
            'sentiment' => $this->sentiment($team),
            'agent_response' => $whatsapp['agent_response'],
            'ai_conversions' => $this->aiConversions($team, $contacts),
            'inbox_waiting' => $whatsapp['inbox_waiting'],
            'list60_resume' => $this->list60Resume($team),
            'advisors' => $this->advisors($team),
            'contact_catalog' => app(WhatsAppThreadCategoryService::class)->catalog($team),
        ];
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     * @return list<array{key: string, label: string, count: int}>
     */
    private function pipeline(Collection $contacts): array
    {
        $counts = $contacts->groupBy(fn (Contact $contact) => (string) ($contact->status?->name ?? ''))
            ->map->count();

        $known = [];
        foreach (self::PIPELINE_STATUS_NAMES as $name)
        {
            $known[] = [
                'key' => $this->statusKey($name),
                'label' => $name,
                'count' => (int) ($counts[$name] ?? 0),
            ];
        }

        $extras = ContactStatus::query()
            ->whereNotIn('name', [...self::PIPELINE_STATUS_NAMES, 'Finalizado'])
            ->orderBy('id')
            ->pluck('name');

        foreach ($extras as $name)
        {
            $count = (int) ($counts[$name] ?? 0);
            if ($count < 1)
            {
                continue;
            }

            $known[] = [
                'key' => $this->statusKey((string) $name),
                'label' => (string) $name,
                'count' => $count,
            ];
        }

        return $known;
    }

    /**
     * @param  list<array{key: string, label: string, count: int}>  $pipeline
     */
    private function pipelineCount(array $pipeline, string $label): int
    {
        foreach ($pipeline as $row)
        {
            if ($row['label'] === $label)
            {
                return $row['count'];
            }
        }

        return 0;
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     * @return list<array{date: string, count: int}>
     */
    private function leadsTrend(Collection $contacts, Carbon $since): array
    {
        $byDay = $contacts
            ->filter(fn (Contact $contact) => $contact->created_at && $contact->created_at->gte($since))
            ->groupBy(fn (Contact $contact) => $contact->created_at->toDateString())
            ->map->count();

        $days = [];
        $cursor = $since->copy();
        while ($cursor->lte(now()->startOfDay()))
        {
            $date = $cursor->toDateString();
            $days[] = [
                'date' => $date,
                'count' => (int) ($byDay[$date] ?? 0),
            ];
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return list<array{id: int, label: string, emoji: string, count: int}>
     */
    private function sentiment(Team $team): array
    {
        $chart = $this->sentimentService->chartDataForTeam($team);
        $byLabel = collect($chart)->keyBy('label');

        $rows = [];
        foreach (self::SENTIMENT_META as $id => $meta)
        {
            $legacyLabel = match ($id)
            {
                1 => 'Muy Negativo',
                2 => 'Negativo',
                3 => 'Neutral',
                4 => 'Positivo',
                5 => 'Muy Positivo',
                default => $meta['label'],
            };

            $rows[] = [
                'id' => $id,
                'label' => $meta['label'],
                'emoji' => $meta['emoji'],
                'count' => (int) ($byLabel[$legacyLabel]['count'] ?? $byLabel[$meta['label']]['count'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     * @return list<array{key: string, label: string, conversions: int, clients: int}>
     */
    private function aiConversions(Team $team, Collection $contacts): array
    {
        $teamDefault = $this->siteAssistant->selectedRoutingKey($team);
        $off = TeamSiteAssistantPromptService::isReservedOffKey($teamDefault);

        $groups = [];
        foreach ($contacts as $contact)
        {
            $statusName = (string) ($contact->status?->name ?? '');
            if (! in_array($statusName, self::CONVERSION_STATUS_NAMES, true))
            {
                continue;
            }

            $key = $contact->inboundChatAssistantPromptKey();
            if ($key === null)
            {
                if ($off || $teamDefault === null)
                {
                    continue;
                }
                $key = $teamDefault;
            }

            if (! isset($groups[$key]))
            {
                $groups[$key] = [
                    'key' => $key,
                    'label' => $this->promptLabel($key),
                    'conversions' => 0,
                    'clients' => 0,
                ];
            }

            $groups[$key]['conversions']++;
            if ($statusName === 'Cliente')
            {
                $groups[$key]['clients']++;
            }
        }

        return collect($groups)
            ->sortByDesc('conversions')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     * @return array{
     *     unread_inbound: int,
     *     agent_response: list<array{key: string, name: string, replies: int, avg_seconds: int, avg_label: string}>,
     *     inbox_waiting: list<array<string, mixed>>
     * }
     */
    private function whatsappMetrics(Team $team, Collection $contacts, Carbon $since): array
    {
        $empty = [
            'unread_inbound' => 0,
            'agent_response' => [],
            'inbox_waiting' => [],
        ];

        $teamNumber = preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom());
        if ($teamNumber === '')
        {
            return $empty;
        }

        $messages = $this->digestCollector
            ->whatsappConversationQueryForTeam($team)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->limit(2500)
            ->get(['id', 'from', 'to', 'direction', 'status', 'body', 'user_id', 'created_at']);

        $unread = $this->digestCollector
            ->whatsappConversationQueryForTeam($team)
            ->where('direction', 'inbound')
            ->where('status', 'received')
            ->count();

        $users = User::query()
            ->whereIn('id', $messages->pluck('user_id')->filter()->unique())
            ->get(['id', 'name'])
            ->keyBy('id');

        $buckets = [];
        $waiting = [];

        foreach ($messages->groupBy(fn (Conversation $row) => $this->peerDigits($row, $teamNumber)) as $peerKey => $thread)
        {
            $peer = (string) $peerKey;
            if ($peer === '' || $peer === $teamNumber)
            {
                continue;
            }

            $pendingInbound = null;
            foreach ($thread->sortBy('created_at') as $row)
            {
                if ($row->direction === 'inbound')
                {
                    $pendingInbound = $row;

                    continue;
                }

                if ($row->direction !== 'outbound' || $pendingInbound === null)
                {
                    continue;
                }

                $seconds = max(0, $pendingInbound->created_at->diffInSeconds($row->created_at));
                $agentKey = $row->user_id ? 'user:'.$row->user_id : 'ai';
                if (! isset($buckets[$agentKey]))
                {
                    $buckets[$agentKey] = [
                        'key' => $agentKey,
                        'name' => $row->user_id
                            ? (string) ($users[$row->user_id]->name ?? 'Agente')
                            : 'Asistente IA',
                        'total_seconds' => 0,
                        'replies' => 0,
                    ];
                }
                $buckets[$agentKey]['total_seconds'] += min($seconds, 86_400);
                $buckets[$agentKey]['replies']++;
                $pendingInbound = null;
            }

            $last = $thread->sortByDesc('created_at')->first();
            if ($last instanceof Conversation && $last->direction === 'inbound')
            {
                $contact = $this->matchContact($contacts, $peer);
                if (in_array($contact?->status?->name, ['Finalizado', 'Perdido'], true))
                {
                    continue;
                }

                $waiting[] = [
                    'phone' => $peer,
                    'contact_id' => $contact?->id,
                    'name' => $contact ? trim($contact->name.' '.($contact->surname ?? '')) : $peer,
                    'email' => $this->contactEmail($contact),
                    'status' => $contact?->status?->name,
                    'status_id' => $contact?->status_id,
                    'category_ids' => $contact?->relationLoaded('categories')
                        ? $contact->categories->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
                        : [],
                    'categories' => $this->contactCategories($contact),
                    'intent' => $this->contactIntent($contact),
                    'summary' => $this->contactInboxSummary($contact),
                    'photo_url' => $this->contactPhotoUrl($team, $contact, $peer),
                    'sentiment' => $this->contactSentiment($contact),
                    'preview' => mb_substr(trim((string) $last->body), 0, 140),
                    'waiting_seconds' => max(0, $last->created_at->diffInSeconds(now())),
                    'waiting_label' => $this->humanDuration($last->created_at->diffInSeconds(now())),
                    'unread' => $last->status === 'received',
                    'inbox_href' => '/inbox?phone='.rawurlencode($peer),
                ];
            }
        }

        $agentResponse = collect($buckets)
            ->map(function (array $bucket): array
            {
                $avg = $bucket['replies'] > 0
                    ? (int) round($bucket['total_seconds'] / $bucket['replies'])
                    : 0;

                return [
                    'key' => $bucket['key'],
                    'name' => $bucket['name'],
                    'replies' => $bucket['replies'],
                    'avg_seconds' => $avg,
                    'avg_label' => $this->humanDuration($avg),
                ];
            })
            ->sortBy('avg_seconds')
            ->values()
            ->all();

        $inboxWaiting = collect($waiting)
            ->sortByDesc('waiting_seconds')
            ->take(20)
            ->values()
            ->all();

        return [
            'unread_inbound' => $unread,
            'agent_response' => $agentResponse,
            'inbox_waiting' => $inboxWaiting,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list60Resume(Team $team): array
    {
        if (! $team->hasModule('list60'))
        {
            return [];
        }

        $today = now()->toDateString();

        return List60::query()
            ->with([
                'contact' => fn ($query) => $query->withoutGlobalScopes()->select([
                    'id', 'name', 'surname', 'phone', 'email', 'team_id', 'data', 'status_id', 'user_id',
                ]),
                'contact.currentSentiment.sentiment',
                'contact.categories:id',
                'contact.user:id,profile_photo_path',
                'status:id,name,label_class',
                'responsible:id,name',
            ])
            ->whereHas('contact', function ($query) use ($team): void
            {
                $query->withoutGlobalScopes()->where('team_id', $team->id);
            })
            ->where(function ($query) use ($today): void
            {
                $query->whereDate('date_next', '<=', $today)
                    ->orWhereHas('status', function ($status): void
                    {
                        $status->whereIn('name', ['Sin respuesta', 'Sin contactar']);
                    });
            })
            ->orderBy('date_next')
            ->limit(30)
            ->get()
            ->filter(fn (List60 $entry) => $entry->contact !== null)
            ->map(function (List60 $entry) use ($team)
            {
                $contact = $entry->contact;
                $phone = preg_replace('/[^0-9]/', '', (string) $contact->phone);
                $days = $entry->date_next
                    ? (int) $entry->date_next->startOfDay()->diffInDays(now()->startOfDay(), false)
                    : 0;
                $reason = $this->resumeReason($entry);
                $sentiment = $this->contactSentiment($contact);

                return [
                    'id' => $entry->id,
                    'contact_id' => $contact->id,
                    'name' => trim($contact->name.' '.($contact->surname ?? '')),
                    'phone' => $phone !== '' ? $phone : null,
                    'email' => $this->contactEmail($contact),
                    'status' => $entry->status?->name,
                    'status_id' => $contact->status_id,
                    'category_ids' => $contact->relationLoaded('categories')
                        ? $contact->categories->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
                        : [],
                    'responsible' => $entry->responsible?->name,
                    'responsible_id' => $entry->responsible_id,
                    'date_next' => $entry->date_next?->toDateString(),
                    'overdue_days' => max(0, $days),
                    'reason' => $reason,
                    'suggestion' => $this->resumeSuggestion($entry, $days, $reason),
                    'photo_url' => $this->contactPhotoUrl($team, $contact, $phone !== '' ? $phone : null),
                    'sentiment' => $sentiment,
                    'sentiment_emoji' => $sentiment['emoji'] ?? null,
                    'inbox_href' => $phone !== ''
                        ? '/inbox?phone='.rawurlencode($phone).'&suggest=list60'
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    private function list60DueCount(Team $team): int
    {
        if (! $team->hasModule('list60'))
        {
            return 0;
        }

        return List60::query()
            ->whereHas('contact', function ($query) use ($team): void
            {
                $query->withoutGlobalScopes()->where('team_id', $team->id);
            })
            ->whereDate('date_next', '<=', now()->toDateString())
            ->count();
    }

    private function appointmentsToday(Team $team): int
    {
        if (! $team->hasModule('calendar') && ! $team->hasModule('today'))
        {
            return 0;
        }

        return CalendarEvent::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereDate('start', now()->toDateString())
            ->count();
    }

    private function resumeSuggestion(List60 $entry, int $days, ?string $reason = null): string
    {
        $name = trim((string) ($entry->contact?->name ?? 'este contacto'));
        $status = (string) ($entry->status?->name ?? '');
        $for = $reason !== null && $reason !== '' ? ' para '.$reason : '';

        if ($status === 'Sin contactar')
        {
            return $name.' quedó en la lista de seguimiento'.$for.'. Todavía no hubo primer toque. Escribí hoy para abrir el hilo.';
        }

        if ($status === 'Sin respuesta')
        {
            return $name.' está en la lista de seguimiento'.$for.' sin respuesta. Retomá con un mensaje corto y una pregunta concreta.';
        }

        if ($days > 0)
        {
            return 'El próximo contacto de '.$name.$for.' venció hace '.$days.' '.($days === 1 ? 'día' : 'días').'. Retomá el diálogo hoy.';
        }

        return 'Hoy toca seguimiento con '.$name.$for.'.';
    }

    private function resumeReason(List60 $entry): ?string
    {
        $labels = $this->listInterestLabels((string) ($entry->notes ?? ''));
        $contactNotes = '';
        if (is_object($entry->contact?->data) && isset($entry->contact->data->notes))
        {
            $contactNotes = (string) $entry->contact->data->notes;
        }
        $labels = array_values(array_unique(array_merge($labels, $this->listInterestLabels($contactNotes))));

        if ($labels !== [])
        {
            return implode(' · ', $labels);
        }

        $fallback = trim((string) ($entry->notes ?? ''));
        if ($fallback === '')
        {
            $fallback = trim($contactNotes);
        }

        return $fallback !== '' ? $fallback : null;
    }

    /**
     * @return list<string>
     */
    private function listInterestLabels(string $text): array
    {
        if ($text === '')
        {
            return [];
        }

        if (preg_match_all('/Inbox \/list:\s*(.+?)(?:\s+[—-]\s+abordar más tarde)?(?:\n|$)/iu', $text, $matches) < 1)
        {
            return [];
        }

        $labels = [];
        foreach ($matches[1] as $label)
        {
            $clean = trim((string) $label);
            if ($clean !== '')
            {
                $labels[] = $clean;
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function advisors(Team $team): array
    {
        $members = User::query()
            ->where(function ($query) use ($team): void
            {
                $query->whereHas('teams', function ($teams) use ($team): void
                {
                    $teams->where('team_id', $team->id);
                })->orWhere('id', $team->user_id);
            })
            ->whereHas('roles', function ($query): void
            {
                $query->whereIn('name', ['admin', 'collaborator', 'employee']);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $owner = $team->user_id ? User::query()->find($team->user_id, ['id', 'name']) : null;
        if ($owner instanceof User && ! $members->contains('id', $owner->id))
        {
            $members->push($owner);
        }

        return $members
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
            ])
            ->all();
    }

    /**
     * @return array{id: int, name: string, emoji: string}|null
     */
    private function contactSentiment(?Contact $contact): ?array
    {
        $mood = $contact?->currentSentiment?->sentiment;
        if ($mood === null)
        {
            return null;
        }

        return [
            'id' => (int) $mood->id,
            'name' => (string) $mood->name,
            'emoji' => (string) $mood->emoji,
        ];
    }

    /**
     * @return list<array{id: int, name: string, color: string|null}>
     */
    private function contactCategories(?Contact $contact): array
    {
        if ($contact === null || ! $contact->relationLoaded('categories'))
        {
            return [];
        }

        return $contact->categories
            ->map(fn ($category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'color' => is_string($category->color) && $category->color !== '' ? $category->color : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int|null, key: string, name: string, emoji: string}|null
     */
    private function contactIntent(?Contact $contact): ?array
    {
        $intent = $contact?->currentSentiment?->intent;
        $key = is_string($intent?->key) ? $intent->key : $this->inboxDigestIntentKey($contact);
        if ($key === null || $key === '')
        {
            return null;
        }

        return [
            'id' => $intent?->id !== null ? (int) $intent->id : null,
            'key' => $key,
            'name' => $this->intentLabel($key, $intent?->name),
            'emoji' => (string) ($intent?->emoji ?: $this->intentEmoji($key)),
        ];
    }

    private function contactInboxSummary(?Contact $contact): ?string
    {
        $digest = $this->inboxDigest($contact);
        $summary = trim((string) ($digest['summary'] ?? ''));

        return $summary !== '' ? $summary : null;
    }

    private function inboxDigestIntentKey(?Contact $contact): ?string
    {
        $key = trim((string) ($this->inboxDigest($contact)['intent_key'] ?? ''));

        return $key !== '' ? $key : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function inboxDigest(?Contact $contact): array
    {
        $data = $contact?->data;
        $digest = is_object($data) ? ($data->inbox_digest ?? null) : (is_array($data) ? ($data['inbox_digest'] ?? null) : null);
        if (is_object($digest))
        {
            return get_object_vars($digest);
        }

        return is_array($digest) ? $digest : [];
    }

    private function intentLabel(string $key, ?string $fallback = null): string
    {
        return match ($key)
        {
            'buy' => 'Comprar',
            'update' => 'Actualizar',
            'work' => 'Resolver',
            'cancel' => 'Cancelar',
            'other' => 'Otro',
            'unclear' => 'Poco clara',
            default => $fallback !== null && $fallback !== '' ? $fallback : $key,
        };
    }

    private function intentEmoji(string $key): string
    {
        return match ($key)
        {
            'buy' => '🛒',
            'update' => '🔄',
            'work' => '🔧',
            'cancel' => '🚪',
            'other' => '💬',
            default => '❔',
        };
    }

    private function contactEmail(?Contact $contact): ?string
    {
        $email = trim((string) ($contact?->email ?? ''));
        if ($email === '' || str_ends_with(strtolower($email), '@chat.placeholder'))
        {
            return null;
        }

        return $email;
    }

    private function contactPhotoUrl(Team $team, ?Contact $contact, ?string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone) ?? '';
        if ($digits !== '')
        {
            $whatsapp = app(WhatsAppProfilePhotoStore::class)->publicUrl((int) $team->id, $digits);
            if (is_string($whatsapp) && $whatsapp !== '')
            {
                return $whatsapp;
            }
        }

        $userPhoto = $contact?->user?->profile_photo_url;

        return is_string($userPhoto) && $userPhoto !== '' ? $userPhoto : null;
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     */
    private function matchContact(Collection $contacts, string $peer): ?Contact
    {
        foreach ($contacts as $contact)
        {
            if (PhoneHelper::digitsBelongToSameLine((string) $contact->phone, $peer))
            {
                return $contact;
            }
        }

        return null;
    }

    private function peerDigits(Conversation $row, string $teamNumber): string
    {
        $from = preg_replace('/[^0-9]/', '', explode(':', (string) $row->from)[0] ?? '');
        $to = preg_replace('/[^0-9]/', '', explode(':', (string) $row->to)[0] ?? '');

        if ($from === $teamNumber)
        {
            return $to;
        }

        if ($to === $teamNumber)
        {
            return $from;
        }

        return $row->direction === 'inbound' ? $from : $to;
    }

    private function promptLabel(string $key): string
    {
        $item = $this->promptCatalog->find($key);

        return $item['section_label'] ?? $key;
    }

    private function statusKey(string $name): string
    {
        return match ($name)
        {
            'Lead' => 'leads',
            'En seguimiento' => 'follow_up',
            'Conversión' => 'conversions',
            'Cliente' => 'clients',
            'Perdido' => 'lost',
            'Finalizado' => 'finished',
            default => strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name) ?? $name),
        };
    }

    private function humanDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds < 60)
        {
            return $seconds.' s';
        }

        $minutes = (int) floor($seconds / 60);
        if ($minutes < 60)
        {
            return $minutes.' min';
        }

        $hours = (int) floor($minutes / 60);
        $restMinutes = $minutes % 60;
        if ($hours < 48)
        {
            return $restMinutes > 0 ? $hours.' h '.$restMinutes.' min' : $hours.' h';
        }

        $days = (int) floor($hours / 24);

        return $days.' d';
    }
}
