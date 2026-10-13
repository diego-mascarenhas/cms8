<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Person;
use App\Services\Contacts\ContactTitleClassifier;
use Illuminate\Console\Command;

class ReclassifyContactRolesCommand extends Command
{
    protected $signature = 'contacts:reclassify-roles
                            {--team= : Only contacts of this team}
                            {--dry-run : Count changes without writing}';

    protected $description = 'Reclassify stored contact and person roles from their titles';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $teamId = $this->option('team') !== null && $this->option('team') !== ''
            ? (int) $this->option('team')
            : null;

        $contacts = $this->reclassifyContacts($teamId, $dryRun);
        $people = $this->reclassifyPeople($dryRun);
        $suffix = $dryRun ? ' (dry-run)' : '';

        $this->info('Contacts updated: '.$contacts.$suffix);
        $this->info('People updated: '.$people.$suffix);

        return self::SUCCESS;
    }

    private function reclassifyContacts(?int $teamId, bool $dryRun): int
    {
        $updated = 0;

        Contact::withoutGlobalScopes()
            ->when($teamId !== null, fn ($query) => $query->where('team_id', $teamId))
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($contacts) use (&$updated, $dryRun): void
            {
                foreach ($contacts as $contact)
                {
                    $role = ContactTitleClassifier::classify(
                        $contact->title,
                        ContactTitleClassifier::organizationFromData($contact->data),
                    );
                    if ($contact->role === $role)
                    {
                        continue;
                    }

                    $updated++;
                    if ($dryRun)
                    {
                        continue;
                    }

                    Contact::withoutGlobalScopes()
                        ->whereKey($contact->id)
                        ->update(['role' => $role]);
                }
            });

        return $updated;
    }

    private function reclassifyPeople(bool $dryRun): int
    {
        $updated = 0;

        Person::query()
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($people) use (&$updated, $dryRun): void
            {
                foreach ($people as $person)
                {
                    $role = ContactTitleClassifier::classify($person->title, $person->organization_name);
                    if ($person->role === $role)
                    {
                        continue;
                    }

                    $updated++;
                    if ($dryRun)
                    {
                        continue;
                    }

                    Person::query()
                        ->whereKey($person->id)
                        ->update(['role' => $role]);
                }
            });

        return $updated;
    }
}
