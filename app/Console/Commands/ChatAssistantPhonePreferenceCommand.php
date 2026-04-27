<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Toggle per-contact inbound WhatsApp assistant (same flag as chat sidebar / CRM contact.data).
 */
class ChatAssistantPhonePreferenceCommand extends Command
{
    protected $signature = 'chat:assistant-phone
                            {state : on or off — enable or disable Humano Assistant auto-replies for this WhatsApp number}
                            {phone : Phone with or without +; must match contact.phone or a linked user phone in digits}
                            {--team= : Team ID when more than one contact could match}';

    protected $description = 'Enable (on) or disable (off) inbound WhatsApp assistant auto-replies for contacts matching this phone number';

    public function handle(): int
    {
        $state = strtolower(trim((string) $this->argument('state')));
        if (! in_array($state, ['on', 'off'], true))
        {
            $this->error('The first argument must be "on" or "off".');

            return self::FAILURE;
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $this->argument('phone'));
        if ($digits === '')
        {
            $this->error('Invalid phone: no digits found.');

            return self::FAILURE;
        }

        $teamOption = $this->option('team');
        $teamId = $teamOption !== null && $teamOption !== '' ? (int) $teamOption : null;
        if ($teamOption !== null && $teamOption !== '' && $teamId < 1)
        {
            $this->error('Invalid --team value.');

            return self::FAILURE;
        }

        if ($teamId !== null && Team::withoutGlobalScopes()->whereKey($teamId)->doesntExist())
        {
            $this->error("No team with id {$teamId}.");

            return self::FAILURE;
        }

        $contacts = $this->resolveContacts($digits, $teamId);
        if ($contacts->isEmpty())
        {
            $this->error('No contact found for that number.'.($teamId === null ? ' Pass --team=ID if several teams share data.' : ''));

            return self::FAILURE;
        }

        if ($teamId === null && $contacts->pluck('team_id')->unique()->count() > 1)
        {
            $this->error('More than one team matches this number. Re-run with --team=TEAM_ID.');
            $this->line('Matching team ids: '.$contacts->pluck('team_id')->unique()->sort()->implode(', '));

            return self::FAILURE;
        }

        $enabled = $state === 'on';
        foreach ($contacts as $contact)
        {
            $this->applyContactAssistantFlag($contact, $enabled);
            $this->line(sprintf(
                'Contact #%d (%s %s, team %s): assistant inbound %s.',
                $contact->id,
                $contact->name ?? '',
                $contact->surname ?? '',
                $contact->team_id,
                $enabled ? 'ON' : 'OFF',
            ));
        }

        $this->info('Done. '.$contacts->count().' contact(s) updated.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Contact>
     */
    private function resolveContacts(string $digits, ?int $teamId): Collection
    {
        $byContactPhone = Contact::withoutGlobalScopes()
            ->when($teamId !== null, fn ($q) => $q->where('team_id', $teamId))
            ->where('phone', $digits)
            ->get();

        if ($byContactPhone->isNotEmpty())
        {
            return $byContactPhone;
        }

        $userIds = $this->userIdsWithPhoneDigits($digits);
        if ($userIds === [])
        {
            return collect();
        }

        return Contact::withoutGlobalScopes()
            ->when($teamId !== null, fn ($q) => $q->where('team_id', $teamId))
            ->whereIn('user_id', $userIds)
            ->get();
    }

    /**
     * @return array<int, int>
     */
    private function userIdsWithPhoneDigits(string $digits): array
    {
        $ids = [];
        User::withoutGlobalScopes()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->select(['id', 'phone'])
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($digits, &$ids)
            {
                foreach ($users as $user)
                {
                    $clean = preg_replace('/[^0-9]/', '', (string) $user->phone);
                    if ($clean === $digits)
                    {
                        $ids[] = (int) $user->id;
                    }
                }
            });

        return array_values(array_unique($ids));
    }

    private function applyContactAssistantFlag(Contact $contact, bool $enabled): void
    {
        $payload = json_encode($contact->data ?? new \stdClass);
        $data = json_decode($payload ?: '{}', true);
        if (! is_array($data))
        {
            $data = [];
        }
        $data['chat_assistant_ai_enabled'] = $enabled;
        $contact->data = $data;
        $contact->save();
    }
}
