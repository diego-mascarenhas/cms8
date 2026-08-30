<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\AutomationFlowSession;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Services\Contacts\TeamContactMatcher;
use Illuminate\Support\Str;

class SiteAssistantVisitorIdentityService
{
    public function __construct(
        protected AutomationFlowEngine $flowEngine,
        protected TeamContactMatcher $contacts,
        protected SiteAssistantConversationService $conversations,
    ) {}

    /**
     * @return array{identified: bool, first_name: string|null, full_name: string|null, email: string|null, contact_id: int|null}
     */
    public function identify(
        Automation $automation,
        string $sessionKey,
        string $email,
        ?string $name = null,
        ?string $phone = null,
    ): array {
        $automation->loadMissing('team');
        $contact = $this->upsertVisitorContact(
            (int) $automation->team_id,
            $email,
            $name,
            $phone,
            (int) ($automation->team?->user_id ?? 0),
        );

        $this->bind($automation, $sessionKey, $contact);

        return $this->recordFromContact($contact);
    }

    public function bind(Automation $automation, string $sessionKey, Contact $contact): AutomationFlowSession
    {
        $session = $this->flowEngine->sessionFor(
            $automation,
            Automation::CHANNEL_API,
            $sessionKey,
        );

        $meta = is_array($session->meta) ? $session->meta : [];
        $meta['contact_id'] = $contact->id;
        $meta['visitor_email'] = strtolower(trim((string) $contact->email));
        $session->meta = $meta;
        $session->last_message_at = now();
        $session->save();
        $this->conversations->bindContactToSessionMessages((int) $automation->team_id, $sessionKey, $contact);

        $sessionPin = trim((string) ($meta[SiteAssistantConversationService::META_INBOUND_PROMPT] ?? ''));
        if ($sessionPin !== '' && ! TeamSiteAssistantPromptService::isReservedOffKey($sessionPin) && ! $contact->inboundChatAssistantPromptKey())
        {
            $contact->pinInboundChatAssistantPrompt($sessionPin);
        }

        return $session;
    }

    /**
     * @return array{identified: bool, first_name: string|null, full_name: string|null, email: string|null, contact_id: int|null}
     */
    public function visitorFor(Automation $automation, ?string $sessionKey): array
    {
        $empty = [
            'identified' => false,
            'first_name' => null,
            'full_name' => null,
            'email' => null,
            'contact_id' => null,
        ];

        if ($sessionKey === null || trim($sessionKey) === '')
        {
            return $empty;
        }

        $session = $this->flowEngine->existingSession(
            $automation,
            Automation::CHANNEL_API,
            $sessionKey,
        );
        $contactId = (int) data_get($session?->meta, 'contact_id', 0);
        if ($contactId < 1)
        {
            return $empty;
        }

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $automation->team_id)
            ->find($contactId);

        return $contact ? $this->recordFromContact($contact) : $empty;
    }

    /**
     * @param  array{identified: bool, first_name: string|null, full_name?: string|null, email?: string|null, contact_id?: int|null}  $record
     * @return array{identified: bool, first_name: string|null}
     */
    public function publicVisitor(array $record): array
    {
        return [
            'identified' => (bool) ($record['identified'] ?? false),
            'first_name' => $record['first_name'] ?? null,
        ];
    }

    public function messageAppendix(Automation $automation, ?string $sessionKey): string
    {
        $visitor = $this->visitorFor($automation, $sessionKey);
        if (! $visitor['identified'])
        {
            return '';
        }

        $name = trim((string) ($visitor['full_name'] ?: $visitor['first_name'] ?: ''));
        $email = trim((string) ($visitor['email'] ?? ''));

        return __('Visitante de la web identificado como :name (:email). Tratá a esta persona como ese cliente. No reveles datos internos del CRM.', [
            'name' => $name !== '' ? $name : __('cliente'),
            'email' => $email !== '' ? $email : __('sin email'),
        ]);
    }

    /**
     * @return array{identified: bool, first_name: string|null, full_name: string|null, email: string|null, contact_id: int|null}
     */
    private function recordFromContact(Contact $contact): array
    {
        $fullName = trim((string) $contact->name.' '.(string) ($contact->surname ?? ''));
        $firstName = trim((string) $contact->name);

        return [
            'identified' => true,
            'first_name' => $firstName !== '' ? $firstName : null,
            'full_name' => $fullName !== '' ? $fullName : null,
            'email' => $contact->email ? strtolower(trim((string) $contact->email)) : null,
            'contact_id' => (int) $contact->id,
        ];
    }

    private function upsertVisitorContact(int $teamId, string $email, ?string $name, ?string $phone, int $ownerUserId): Contact
    {
        $normalizedEmail = strtolower(trim($email));
        $phoneDigits = $this->digitsOrNull($phone);
        $existing = $this->contacts->findExisting($teamId, $normalizedEmail, null, null);

        if ($existing)
        {
            $this->fillMissingVisitorFields($existing, $name, $phoneDigits);

            return $existing->fresh() ?? $existing;
        }

        if ($ownerUserId < 1)
        {
            throw new \RuntimeException('Team owner is required to capture a site assistant visitor.');
        }

        [$firstName, $surname] = $this->splitVisitorName($name, $normalizedEmail);

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => $firstName,
            'surname' => $surname,
            'email' => $normalizedEmail,
            'phone' => $phoneDigits,
            'status_id' => $this->leadStatusId(),
            'creator_id' => $ownerUserId,
            'responsible_id' => $ownerUserId,
            'data' => (object) [
                'source' => 'site_assistant',
                'captured_at' => now()->toIso8601String(),
            ],
        ]);

        $defaultCategoryId = config('custom.default_contact_category_id');
        if ($defaultCategoryId)
        {
            $exists = Category::withoutGlobalScopes()
                ->where('id', (int) $defaultCategoryId)
                ->where('team_id', $teamId)
                ->exists();
            if ($exists)
            {
                $contact->categories()->syncWithoutDetaching([(int) $defaultCategoryId]);
            }
        }

        return $contact;
    }

    private function fillMissingVisitorFields(Contact $contact, ?string $name, ?int $phoneDigits): void
    {
        $updates = [];
        if ($this->blank($contact->name) && $name !== null && trim($name) !== '')
        {
            [$firstName, $surname] = $this->contacts->splitFullName($name);
            $updates['name'] = $firstName;
            if ($this->blank($contact->surname) && $surname)
            {
                $updates['surname'] = $surname;
            }
        }

        if ($contact->phone === null && $phoneDigits !== null)
        {
            $updates['phone'] = $phoneDigits;
        }

        if ($updates !== [])
        {
            $contact->fill($updates)->save();
        }
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function splitVisitorName(?string $name, string $email): array
    {
        if ($name !== null && trim($name) !== '')
        {
            return $this->contacts->splitFullName($name);
        }

        $local = Str::before($email, '@');

        return [$local !== '' ? $local : __('Visitante'), null];
    }

    private function leadStatusId(): ?int
    {
        $id = ContactStatus::query()->where('name', 'Lead')->value('id')
            ?? ContactStatus::query()->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function digitsOrNull(?string $phone): ?int
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        return strlen($digits) >= 6 ? (int) $digits : null;
    }

    private function blank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }
}
