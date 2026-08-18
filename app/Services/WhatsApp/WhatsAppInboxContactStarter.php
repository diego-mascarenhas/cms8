<?php

namespace App\Services\WhatsApp;

use App\Models\Contact;
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
     * @return array{created: bool, contact: array{id: int, contact_id: int, name: string, phone: string}}
     */
    public function start(User $user, Team $team, string $name, string $phone): array
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
            'status_id' => 1,
        ]);

        return [
            'created' => true,
            'contact' => $this->present($contact, $digits),
        ];
    }

    /**
     * @return array{id: int, contact_id: int, name: string, phone: string}
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
        ];
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
