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
            ->with(['status:id,name', 'currentSentiment.sentiment'])
            ->get(['id', 'name', 'surname', 'phone', 'email', 'status_id', 'data', 'created_at', 'responsible_id']);

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
                $waiting[] = [
                    'phone' => $peer,
                    'contact_id' => $contact?->id,
                    'name' => $contact ? trim($contact->name.' '.($contact->surname ?? '')) : $peer,
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
                    'id', 'name', 'surname', 'phone', 'email', 'team_id',
                ]),
                'contact.currentSentiment.sentiment',
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
            ->map(function (List60 $entry)
            {
                $contact = $entry->contact;
                $phone = preg_replace('/[^0-9]/', '', (string) $contact->phone);
                $days = $entry->date_next
                    ? (int) $entry->date_next->startOfDay()->diffInDays(now()->startOfDay(), false)
                    : 0;

                return [
                    'id' => $entry->id,
                    'contact_id' => $contact->id,
                    'name' => trim($contact->name.' '.($contact->surname ?? '')),
                    'phone' => $phone !== '' ? $phone : null,
                    'status' => $entry->status?->name,
                    'responsible' => $entry->responsible?->name,
                    'date_next' => $entry->date_next?->toDateString(),
                    'overdue_days' => max(0, $days),
                    'suggestion' => $this->resumeSuggestion($entry, $days),
                    'sentiment_emoji' => $contact->currentSentiment?->sentiment?->emoji,
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

    private function resumeSuggestion(List60 $entry, int $days): string
    {
        $name = trim((string) ($entry->contact?->name ?? 'este contacto'));
        $status = (string) ($entry->status?->name ?? '');

        if ($status === 'Sin contactar')
        {
            return 'Todavía no hubo primer toque con '.$name.'. Escribí hoy para abrir el hilo.';
        }

        if ($status === 'Sin respuesta')
        {
            return $name.' está en Lista 60 sin respuesta. Retomá con un mensaje corto y una pregunta concreta.';
        }

        if ($days > 0)
        {
            return 'El próximo contacto de '.$name.' venció hace '.$days.' '.($days === 1 ? 'día' : 'días').'. Retomá el diálogo hoy.';
        }

        return 'Hoy toca seguimiento con '.$name.'.';
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
