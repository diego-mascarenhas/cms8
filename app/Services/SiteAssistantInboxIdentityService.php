<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\Contact;
use App\Models\SiteAssistantMessage;
use App\Models\Team;
use App\Models\User;
use App\Services\Contacts\TeamContactMatcher;
use Illuminate\Support\Collection;

class SiteAssistantInboxIdentityService
{
    public const SLASH = '/identificar';

    public function __construct(
        protected TeamContactMatcher $contacts,
    ) {}

    public function askMessage(): string
    {
        return __('Para seguir, ¿me pasás tu nombre, email y teléfono?');
    }

    public function staffReplyBody(string $body): string
    {
        $trim = trim($body);
        if ($this->isIdentifySlash($trim))
        {
            return $this->askMessage();
        }

        return $trim;
    }

    public function isIdentifySlash(string $body): bool
    {
        return preg_match('#^/identificar(?:\s|$)#iu', trim($body)) === 1;
    }

    public function isClientClaim(string $body): bool
    {
        return preg_match('/^ya soy cliente[.!]?\s*$/iu', trim($body)) === 1;
    }

    public function identifiedMessage(): string
    {
        return __('Listo, ya te tenemos identificado. ¿En qué te ayudo?');
    }

    /**
     * @return array{reply: string, visitor: array{identified: bool, first_name: string|null}}|null
     */
    public function handlePublicMessage(
        Automation $automation,
        string $sessionKey,
        string $message,
        SiteAssistantVisitorIdentityService $identity,
        SiteAssistantConversationService $conversations,
        ?string $channel = null,
    ): ?array {
        $visitor = $identity->visitorFor($automation, $sessionKey);
        if ($visitor['identified'])
        {
            return null;
        }

        if ($this->isClientClaim($message))
        {
            $reply = $this->askMessage();
            $conversations->recordTurn($automation, $sessionKey, $message, $reply, $channel);

            return [
                'reply' => $reply,
                'visitor' => $identity->publicVisitor($visitor),
            ];
        }

        if ($conversations->lastTeamBody($automation, $sessionKey) !== $this->askMessage())
        {
            return null;
        }

        $hints = $this->extractHints([$message]);
        if ($hints['email'] === null && $hints['phone'] === null)
        {
            return null;
        }

        $record = $identity->identify(
            $automation,
            $sessionKey,
            (string) ($hints['email'] ?? ''),
            $hints['name'],
            $hints['phone'],
        );
        $reply = $this->identifiedMessage();
        $conversations->recordTurn($automation, $sessionKey, $message, $reply, $channel);

        return [
            'reply' => $reply,
            'visitor' => $identity->publicVisitor($record),
        ];
    }

    /**
     * @param  Collection<int, SiteAssistantMessage>  $messages
     * @return array{identified: bool, extracted: array{name: string|null, email: string|null, phone: string|null}, suggestions: list<array{id: int, name: string, email: string|null, phone: string|null}>, can_create: bool}
     */
    public function present(Team $team, Collection $messages, ?Contact $contact): array
    {
        if ($contact)
        {
            return [
                'identified' => true,
                'extracted' => ['name' => null, 'email' => null, 'phone' => null],
                'suggestions' => [],
                'can_create' => false,
            ];
        }

        $extracted = $this->extractHints($this->visitorBodies($messages));
        $suggestions = $this->suggestionsFor($team, $extracted);

        return [
            'identified' => false,
            'extracted' => $extracted,
            'suggestions' => $suggestions,
            'can_create' => $extracted['email'] !== null || $extracted['phone'] !== null,
        ];
    }

    /**
     * @param  list<int>|null  $allowedContactIds
     * @param  array{name?: string|null, email?: string|null, phone?: string|null}|null  $fields
     */
    public function assign(
        Team $team,
        string $sessionKey,
        ?int $contactId,
        bool $create,
        ?array $allowedContactIds,
        ?User $actor,
        ?array $fields = null,
    ): ?Contact {
        $messages = SiteAssistantMessage::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('session_key', $sessionKey)
            ->orderBy('id')
            ->get();
        if ($messages->isEmpty())
        {
            return null;
        }

        $anchor = $messages->last();
        $automation = Automation::withoutGlobalScopes()->find($anchor?->automation_id);
        if (! $automation)
        {
            return null;
        }

        $identity = app(SiteAssistantVisitorIdentityService::class);

        if ($contactId !== null && $contactId > 0)
        {
            if ($allowedContactIds !== null && ! in_array($contactId, $allowedContactIds, true))
            {
                return null;
            }

            $contact = Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->find($contactId);
            if (! $contact)
            {
                return null;
            }
        } elseif ($create)
        {
            $extracted = $this->extractHints($this->visitorBodies($messages));
            $name = $this->filledString($fields['name'] ?? null) ?? $extracted['name'];
            $email = $this->filledString($fields['email'] ?? null) ?? $extracted['email'];
            $phone = $this->filledString($fields['phone'] ?? null) ?? $extracted['phone'];
            if ($email === null && $phone === null)
            {
                return null;
            }

            $ownerId = (int) ($actor?->id ?: $team->user_id);
            $contact = $identity->upsertVisitorContact(
                (int) $team->id,
                (string) ($email ?? ''),
                $name,
                $phone,
                $ownerId,
            );
        } else
        {
            return null;
        }

        $identity->bind($automation, $sessionKey, $contact);

        return $contact->fresh() ?? $contact;
    }

    /**
     * @param  list<string>  $bodies
     * @return array{name: string|null, email: string|null, phone: string|null}
     */
    public function extractHints(array $bodies): array
    {
        $text = trim(implode("\n", $bodies));
        $email = null;
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $match) === 1)
        {
            $email = strtolower($match[0]);
        }

        $phone = null;
        if (preg_match_all('/\+?\d[\d\s().\-]{5,}\d/', $text, $matches) > 0)
        {
            foreach ($matches[0] as $candidate)
            {
                $digits = preg_replace('/\D+/', '', (string) $candidate) ?: '';
                if (strlen($digits) >= 6)
                {
                    $phone = $digits;
                    break;
                }
            }
        }

        $leftover = $text;
        if ($email)
        {
            $leftover = str_ireplace($email, ' ', $leftover);
        }
        if ($phone)
        {
            $leftover = preg_replace('/\+?\d[\d\s().\-]{5,}\d/', ' ', $leftover) ?? $leftover;
        }
        $leftover = trim(preg_replace('/[^\p{L}\s]/u', ' ', $leftover) ?? '');
        $leftover = trim(preg_replace('/\s+/u', ' ', $leftover) ?? '');
        $name = $this->usableName($leftover);

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];
    }

    /**
     * @param  Collection<int, SiteAssistantMessage>  $messages
     * @return list<string>
     */
    private function visitorBodies(Collection $messages): array
    {
        return $messages
            ->where('role', SiteAssistantMessage::ROLE_VISITOR)
            ->pluck('body')
            ->map(fn ($body) => trim((string) $body))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array{name: string|null, email: string|null, phone: string|null}  $extracted
     * @return list<array{id: int, name: string, email: string|null, phone: string|null}>
     */
    private function suggestionsFor(Team $team, array $extracted): array
    {
        $found = collect();
        $phone = $extracted['phone'] !== null ? (int) $extracted['phone'] : null;

        $direct = $this->contacts->findExisting(
            (int) $team->id,
            $extracted['email'],
            $phone,
            null,
        );
        if ($direct)
        {
            $found->push($direct);
        }

        foreach (array_filter([$extracted['email'], $extracted['phone']]) as $query)
        {
            $found = $found->concat($this->contacts->search((int) $team->id, (string) $query, 3));
        }

        return $found
            ->unique('id')
            ->take(5)
            ->map(fn (Contact $contact) => $this->presentContact($contact))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, email: string|null, phone: string|null}
     */
    private function presentContact(Contact $contact): array
    {
        $name = trim((string) $contact->name.' '.(string) ($contact->surname ?? ''));

        return [
            'id' => (int) $contact->id,
            'name' => $name !== '' ? $name : __('Visitante'),
            'email' => $contact->email ? strtolower(trim((string) $contact->email)) : null,
            'phone' => $contact->phone ? (string) $contact->phone : null,
        ];
    }

    private function filledString(mixed $value): ?string
    {
        if (! is_string($value))
        {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function usableName(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '')
        {
            return null;
        }

        $stop = ['hola', 'ok', 'si', 'sí', 'dale', 'gracias', 'perfecto', 'buenas', 'hey', 'hello', 'hi'];
        $words = preg_split('/\s+/u', mb_strtolower($value)) ?: [];
        $meaningful = array_values(array_filter($words, fn (string $word) => ! in_array($word, $stop, true)));
        if ($meaningful === [] || count($meaningful) > 4)
        {
            return null;
        }

        $name = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $name !== '' ? $name : null;
    }
}
