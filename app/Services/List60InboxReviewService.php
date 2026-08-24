<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppChatArchiveService;
use App\Support\List60OutreachPromptDefaults;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class List60InboxReviewService
{
    public const ROUTING_KEY_ALTA = 'list60:alta';

    public const CANDIDATE_LIMIT = 20;

    public function __construct(
        private readonly DailyTeamDigestMetricsCollector $digestCollector,
        private readonly ChatAssistantReplyService $replyService,
        private readonly WhatsAppChatArchiveService $archives,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     section_label: string,
     *     helper_text: string|null,
     *     prompt_instruction: string,
     *     default_instruction: string,
     *     available: bool
     * }
     */
    public function promptPayload(Team $team): array
    {
        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);
        $prompt = Prompt::findByRoutingKey(self::ROUTING_KEY_ALTA, (int) $team->id);
        $default = List60OutreachPromptDefaults::altaInstruction();

        return [
            'key' => self::ROUTING_KEY_ALTA,
            'section_label' => $prompt?->section_label ?: 'Lista de 60: quién entra',
            'helper_text' => $prompt?->helper_text,
            'prompt_instruction' => $prompt && trim((string) $prompt->prompt_instruction) !== ''
                ? (string) $prompt->prompt_instruction
                : $default,
            'default_instruction' => $default,
            'available' => $prompt !== null,
        ];
    }

    public function updateInstruction(Team $team, string $instruction): Prompt
    {
        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);
        $prompt = Prompt::findByRoutingKey(self::ROUTING_KEY_ALTA, (int) $team->id);
        if (! $prompt)
        {
            throw new InvalidArgumentException(__('team_settings.list60_prompt.missing_prompt'));
        }

        $prompt->prompt_instruction = trim($instruction);
        $prompt->is_active = true;
        $prompt->save();

        return $prompt;
    }

    /**
     * @return array{reviewed: int, items: list<array<string, mixed>>}
     */
    public function review(User $user): array
    {
        $team = $user->currentTeam;
        if (! $team)
        {
            throw new InvalidArgumentException(__('No hay equipo actual.'));
        }

        $candidates = $this->candidates($team);
        if ($candidates === [])
        {
            return ['reviewed' => 0, 'items' => []];
        }

        $classifications = $this->classify($user, $team, $candidates);

        $items = [];
        foreach ($candidates as $candidate)
        {
            $phone = (string) $candidate['phone'];
            $parsed = $classifications[$phone] ?? [
                'action' => $candidate['on_list60'] ? 'already_on_list' : 'leave',
                'reason' => __('team_settings.list60_prompt.unclassified'),
                'suggested_message' => '',
                'suggested_responsible_id' => null,
            ];

            $advisor = $this->resolveAdvisor($candidate, $parsed['suggested_responsible_id']);

            $items[] = [
                'phone' => $candidate['phone'],
                'contact_id' => $candidate['contact_id'],
                'name' => $candidate['name'],
                'preview' => $candidate['preview'],
                'waiting_seconds' => $candidate['waiting_seconds'],
                'is_archived' => $candidate['is_archived'],
                'on_list60' => $candidate['on_list60'],
                'list60_status' => $candidate['list60_status'],
                'unread' => $candidate['unread'],
                'inbox_href' => $this->inboxHref($phone, $parsed['suggested_message']),
                'responsible_id' => $candidate['responsible_id'],
                'responsible_name' => $candidate['responsible_name'],
                'needs_advisor' => $candidate['needs_advisor'],
                'suggested_responsible_id' => $advisor['id'],
                'suggested_responsible_name' => $advisor['name'],
                'action' => $parsed['action'],
                'reason' => $parsed['reason'],
                'suggested_message' => $parsed['suggested_message'],
            ];
        }

        return [
            'reviewed' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array<string, array{action: string, reason: string, suggested_message: string, suggested_responsible_id: int|null}>
     */
    private function classify(User $user, Team $team, array $candidates): array
    {
        $payload = $this->promptPayload($team);
        $instruction = $this->buildReviewInstruction($payload['prompt_instruction'], $candidates);

        $reply = $this->replyService->getReply(
            $instruction,
            [],
            (int) $team->id,
            false,
            (int) $user->id,
            null,
            null,
            null,
            true,
        );

        if (! ($reply['success'] ?? false))
        {
            throw new InvalidArgumentException((string) ($reply['message'] ?? __('Error')));
        }

        return $this->parseClassifications((string) ($reply['text'] ?? ''), $candidates);
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    private function buildReviewInstruction(string $objective, array $candidates): string
    {
        $lines = [
            trim($objective),
            '',
            'Candidatos del inbox (incluidos archivados). Clasificá todos.',
            '',
        ];

        foreach ($candidates as $index => $candidate)
        {
            $n = $index + 1;
            $onList = $candidate['on_list60'] ? 'sí' : 'no';
            $archived = $candidate['is_archived'] ? 'sí' : 'no';
            $lines[] = $n.'. Teléfono: '.$candidate['phone'];
            $lines[] = '   Nombre: '.$candidate['name'];
            $lines[] = '   Ya en Lista 60: '.$onList.($candidate['list60_status'] ? ' ('.$candidate['list60_status'].')' : '');
            $lines[] = '   Archivado: '.$archived;
            $lines[] = $candidate['needs_advisor']
                ? '   Asesor: ninguno. Elegí a quien dio la respuesta acertada.'
                : '   Asesor: '.$candidate['responsible_name'].' (id '.$candidate['responsible_id'].'). No lo cambies.';
            $lines[] = '   Último mensaje: '.$candidate['preview'];
            if ($candidate['recent_messages'] !== [])
            {
                $lines[] = '   Hilo reciente:';
                foreach ($candidate['recent_messages'] as $message)
                {
                    $who = $message['direction'] === 'inbound'
                        ? 'Cliente'
                        : ($message['agent_name']
                            ? 'Equipo ('.$message['agent_name'].', id '.$message['user_id'].')'
                            : 'Asistente IA');
                    $lines[] = '   - '.$who.': '.$message['body'];
                }
            }
            $lines[] = '';
        }

        $lines[] = 'Idioma: español, salvo que el hilo indique otro.';
        $lines[] = 'Responde solo con un objeto JSON (sin markdown ni comentarios).';
        $lines[] = 'Clave "items": array con un objeto por candidato. Cada objeto:';
        $lines[] = '- "phone": el teléfono exacto del candidato';
        $lines[] = '- "action": list60 | leave | already_on_list';
        $lines[] = '- "reason": una frase';
        $lines[] = '- "suggested_message": WhatsApp de 1 o 2 frases, humano, máximo 220 caracteres (vacío solo si no hay nada que decir)';
        $lines[] = '- "suggested_responsible_id": id numérico del asesor recomendado, o null si ya tiene asesor o no hay humano en el hilo';
        $lines[] = '';
        $lines[] = List60OutreachPromptDefaults::whatsappBrevityRules();

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array<string, array{action: string, reason: string, suggested_message: string, suggested_responsible_id: int|null}>
     */
    private function parseClassifications(string $text, array $candidates): array
    {
        $known = [];
        foreach ($candidates as $candidate)
        {
            $known[(string) $candidate['phone']] = true;
        }

        $decoded = $this->decodeJsonPayload(trim($text));
        $rows = [];
        if (is_array($decoded))
        {
            if (isset($decoded['items']) && is_array($decoded['items']))
            {
                $rows = $decoded['items'];
            } elseif (array_is_list($decoded))
            {
                $rows = $decoded;
            }
        }

        $mapped = [];
        foreach ($rows as $row)
        {
            if (! is_array($row))
            {
                continue;
            }

            $phone = preg_replace('/[^0-9]/', '', (string) ($row['phone'] ?? ''));
            if ($phone === '' || ! isset($known[$phone]))
            {
                foreach (array_keys($known) as $candidatePhone)
                {
                    if (PhoneHelper::digitsBelongToSameLine((string) $candidatePhone, $phone))
                    {
                        $phone = (string) $candidatePhone;
                        break;
                    }
                }
            }

            if ($phone === '' || ! isset($known[$phone]) || isset($mapped[$phone]))
            {
                continue;
            }

            $action = (string) ($row['action'] ?? 'leave');
            if (! in_array($action, ['list60', 'leave', 'already_on_list'], true))
            {
                $action = 'leave';
            }

            $mapped[$phone] = [
                'action' => $action,
                'reason' => trim((string) ($row['reason'] ?? '')),
                'suggested_message' => trim((string) ($row['suggested_message'] ?? '')),
                'suggested_responsible_id' => $this->parseResponsibleId($row['suggested_responsible_id'] ?? null),
            ];
        }

        return $mapped;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function candidates(Team $team): array
    {
        $teamNumber = preg_replace('/[^0-9]/', '', (string) $team->getWhatsAppFrom());
        if ($teamNumber === '')
        {
            return [];
        }

        $since = now()->subDays(AssistantCommercialStatsService::PERIOD_DAYS)->startOfDay();
        $contacts = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->with([
                'list60:id,contact_id,status_id',
                'list60.status:id,name',
                'responsible:id,name',
            ])
            ->get(['id', 'name', 'surname', 'phone', 'team_id', 'responsible_id']);

        $messages = $this->digestCollector
            ->whatsappConversationQueryForTeam($team)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->limit(2500)
            ->get(['id', 'from', 'to', 'direction', 'status', 'body', 'user_id', 'created_at']);

        $users = User::query()
            ->whereIn('id', $messages->pluck('user_id')->filter()->unique()->merge(
                $contacts->pluck('responsible_id')->filter(),
            ))
            ->get(['id', 'name'])
            ->keyBy('id');

        $archivedPhones = $this->archives->archivedPhoneSet((int) $team->id);
        $waiting = [];

        foreach ($messages->groupBy(fn (Conversation $row) => $this->peerDigits($row, $teamNumber)) as $peerKey => $thread)
        {
            $peer = (string) $peerKey;
            if ($peer === '' || $peer === $teamNumber)
            {
                continue;
            }

            $ordered = $thread->sortBy('created_at')->values();
            $last = $ordered->last();
            if (! $last instanceof Conversation || $last->direction !== 'inbound')
            {
                continue;
            }

            $contact = $this->matchContact($contacts, $peer);
            $onList = $contact?->list60 !== null;
            $fallback = $this->fallbackAdvisor($ordered, $users);
            $recent = $ordered
                ->reverse()
                ->take(8)
                ->reverse()
                ->map(fn (Conversation $row): array => [
                    'direction' => (string) $row->direction,
                    'body' => mb_substr(trim((string) $row->body), 0, 240),
                    'user_id' => $row->user_id ? (int) $row->user_id : null,
                    'agent_name' => $row->user_id ? (string) ($users[$row->user_id]->name ?? 'Agente') : null,
                ])
                ->values()
                ->all();

            $waiting[] = [
                'phone' => $peer,
                'contact_id' => $contact?->id,
                'name' => $contact ? trim($contact->name.' '.($contact->surname ?? '')) : $peer,
                'preview' => mb_substr(trim((string) $last->body), 0, 140),
                'waiting_seconds' => max(0, $last->created_at->diffInSeconds(now())),
                'is_archived' => isset($archivedPhones[$peer]),
                'on_list60' => $onList,
                'list60_status' => $onList ? ($contact?->list60?->status?->name) : null,
                'unread' => $last->status === 'received',
                'inbox_href' => '/inbox?phone='.rawurlencode($peer),
                'responsible_id' => $contact?->responsible_id,
                'responsible_name' => $contact?->responsible?->name,
                'needs_advisor' => $contact === null || $contact->responsible_id === null,
                'eligible_advisor_ids' => $this->eligibleAdvisorIds($ordered),
                'fallback_responsible_id' => $fallback['id'],
                'fallback_responsible_name' => $fallback['name'],
                'recent_messages' => $recent,
            ];
        }

        return collect($waiting)
            ->sortByDesc('waiting_seconds')
            ->take(self::CANDIDATE_LIMIT)
            ->values()
            ->all();
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

    /**
     * @param  array<string, mixed>  $candidate
     * @return array{id: int|null, name: string|null}
     */
    private function resolveAdvisor(array $candidate, ?int $suggestedId): array
    {
        if (! $candidate['needs_advisor'])
        {
            return ['id' => null, 'name' => null];
        }

        $eligible = $candidate['eligible_advisor_ids'];
        if ($suggestedId !== null && in_array($suggestedId, $eligible, true))
        {
            foreach ($candidate['recent_messages'] as $message)
            {
                if ((int) ($message['user_id'] ?? 0) === $suggestedId && $message['agent_name'])
                {
                    return ['id' => $suggestedId, 'name' => $message['agent_name']];
                }
            }

            if ((int) $candidate['fallback_responsible_id'] === $suggestedId)
            {
                return [
                    'id' => $suggestedId,
                    'name' => $candidate['fallback_responsible_name'],
                ];
            }
        }

        return [
            'id' => $candidate['fallback_responsible_id'],
            'name' => $candidate['fallback_responsible_name'],
        ];
    }

    /**
     * @param  Collection<int, Conversation>  $ordered
     * @param  Collection<int, User>  $users
     * @return array{id: int|null, name: string|null}
     */
    private function fallbackAdvisor(Collection $ordered, Collection $users): array
    {
        $successfulId = null;
        $pendingOutboundId = null;

        foreach ($ordered as $row)
        {
            if ($row->direction === 'outbound' && $row->user_id)
            {
                $pendingOutboundId = (int) $row->user_id;

                continue;
            }

            if ($row->direction === 'inbound' && $pendingOutboundId !== null)
            {
                $successfulId = $pendingOutboundId;
                $pendingOutboundId = null;
            }
        }

        $userId = $successfulId;
        if ($userId === null)
        {
            $lastHuman = $ordered->reverse()->first(
                fn (Conversation $row): bool => $row->direction === 'outbound' && $row->user_id,
            );
            $userId = $lastHuman?->user_id ? (int) $lastHuman->user_id : null;
        }

        if ($userId === null || ! isset($users[$userId]))
        {
            return ['id' => null, 'name' => null];
        }

        return ['id' => $userId, 'name' => (string) $users[$userId]->name];
    }

    /**
     * @param  Collection<int, Conversation>  $ordered
     * @return list<int>
     */
    private function eligibleAdvisorIds(Collection $ordered): array
    {
        return $ordered
            ->filter(fn (Conversation $row): bool => $row->direction === 'outbound' && $row->user_id)
            ->map(fn (Conversation $row): int => (int) $row->user_id)
            ->unique()
            ->values()
            ->all();
    }

    private function parseResponsibleId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false)
        {
            return null;
        }

        if (! is_numeric($value))
        {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonPayload(string $raw): ?array
    {
        if ($raw === '')
        {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded))
        {
            return $decoded;
        }

        if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/m', $raw, $matches))
        {
            $decoded = json_decode(trim($matches[1]), true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function inboxHref(string $phone, string $suggestedMessage = ''): string
    {
        $href = '/inbox?phone='.rawurlencode($phone);
        $draft = trim($suggestedMessage);
        if ($draft === '' || strlen($draft) > 1500)
        {
            return $href;
        }

        return $href.'&draft='.rawurlencode($draft);
    }
}
