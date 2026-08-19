<?php

namespace App\Services\WhatsApp;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Team;
use App\Models\User;
use App\Services\Contacts\TeamContactMatcher;
use App\Services\TeamInboundAssistantPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WhatsAppInboxContactStarter
{
    public function __construct(
        private TeamContactMatcher $matcher,
        private TeamInboundAssistantPolicy $inboundPolicy,
    ) {}

    public static function normalizeInboxPhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if (strlen($digits) === 9 && preg_match('/^[67]/', $digits))
        {
            return '34'.$digits;
        }

        return $digits;
    }

    /**
     * @return list<array{id: int, contact_id: int, name: string, phone: string}>
     */
    public function search(int $teamId, string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2)
        {
            return [];
        }

        return $this->matcher->search($teamId, $query, 8)
            ->map(function (Contact $contact)
            {
                $digits = self::normalizeInboxPhone((string) $contact->phone);

                return $this->present($contact, $digits);
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<int|string>  $categoryIds
     * @return array{created: bool, contact: array{id: int, contact_id: int, name: string, phone: string, status_id: int|null}}
     */
    public function start(User $user, Team $team, string $name, string $phone, ?int $statusId = null, array $categoryIds = []): array
    {
        $digits = self::normalizeInboxPhone($phone);
        if ($digits === '')
        {
            throw new HttpException(422, __('El teléfono es obligatorio.'));
        }

        if ($this->inboundPolicy->isBlacklistedWhatsAppPhone($team, $digits))
        {
            throw new HttpException(403, __('Forbidden'));
        }

        $existing = $this->findContactByPhone((int) $team->id, $digits);
        if ($existing !== null)
        {
            if (! Gate::forUser($user)->allows('view', $existing))
            {
                throw new AuthorizationException;
            }

            return [
                'created' => false,
                'contact' => $this->present($existing, $digits),
            ];
        }

        if (! Gate::forUser($user)->allows('create', Contact::class))
        {
            throw new AuthorizationException;
        }

        [$firstName, $surname] = $this->matcher->splitFullName($name);
        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'name' => $firstName !== '' ? $firstName : $name,
            'surname' => $surname,
            'phone' => $digits,
            'status_id' => $this->resolvedStatusId($statusId),
        ]);

        if ($categoryIds !== [])
        {
            app(WhatsAppThreadCategoryService::class)->replace($team, $contact, $categoryIds);
        }

        return [
            'created' => true,
            'contact' => $this->present($contact->fresh(), $digits),
        ];
    }

    /**
     * Phone stays as stored. Name, CRM status and contact categories can change.
     *
     * @param  list<int|string>  $categoryIds
     * @return array{contact: array{id: int, contact_id: int, name: string, phone: string, status_id: int|null}, thread_categories: array{contact_id: int|null, selected: list<array{id: int, name: string}>, available: list<array{id: int, name: string}>}}
     */
    public function update(User $user, Team $team, Contact $contact, string $name, int $statusId, array $categoryIds): array
    {
        if (! Gate::forUser($user)->allows('update', $contact))
        {
            throw new AuthorizationException;
        }

        if (! ContactStatus::query()->whereKey($statusId)->exists())
        {
            throw new HttpException(422, __('The selected status is invalid.'));
        }

        [$firstName, $surname] = $this->matcher->splitFullName($name);
        $contact->forceFill([
            'name' => $firstName !== '' ? $firstName : $name,
            'surname' => $surname,
            'status_id' => $statusId,
        ])->save();

        $digits = self::normalizeInboxPhone((string) $contact->phone);
        $categories = app(WhatsAppThreadCategoryService::class)->replace($team, $contact, $categoryIds);

        return [
            'contact' => $this->present($contact->fresh(), $digits),
            'thread_categories' => $categories,
        ];
    }

    /**
     * @return array{id: int, contact_id: int, name: string, phone: string, status_id: int|null}
     */
    private function present(Contact $contact, string $fallbackPhone): array
    {
        $display = trim($contact->name.' '.(string) ($contact->surname ?? ''));
        $storedPhone = preg_replace('/[^0-9]/', '', (string) $contact->phone) ?? '';

        return [
            'id' => (int) $contact->id,
            'contact_id' => (int) $contact->id,
            'name' => $display !== '' ? $display : $fallbackPhone,
            'phone' => $storedPhone !== '' ? $storedPhone : $fallbackPhone,
            'status_id' => $contact->status_id !== null ? (int) $contact->status_id : null,
        ];
    }

    private function resolvedStatusId(?int $statusId): int
    {
        if ($statusId !== null && ContactStatus::query()->whereKey($statusId)->exists())
        {
            return $statusId;
        }

        $leadId = ContactStatus::query()->where('name', 'Lead')->value('id');

        return $leadId !== null ? (int) $leadId : 1;
    }

    private function findContactByPhone(int $teamId, string $digits): ?Contact
    {
        return Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where(function ($query) use ($digits)
            {
                $query->where('phone', $digits);
                if (strlen($digits) === 11 && str_starts_with($digits, '34'))
                {
                    $query->orWhere('phone', substr($digits, -9));
                }
                if (strlen($digits) === 9)
                {
                    $query->orWhere('phone', '34'.$digits);
                }
            })
            ->orderBy('id')
            ->first();
    }
}
