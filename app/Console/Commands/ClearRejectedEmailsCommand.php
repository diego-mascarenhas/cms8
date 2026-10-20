<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\MessageDelivery;
use Illuminate\Console\Command;

class ClearRejectedEmailsCommand extends Command
{
    protected $signature = 'mailer:clear-rejected-emails
                            {--team= : Only contacts of this team}
                            {--dry-run : Count matches without writing}';

    protected $description = 'Clear emails already rejected because the mailbox does not exist, and keep the old address in JSON';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $teamId = $this->option('team') !== null && $this->option('team') !== ''
            ? (int) $this->option('team')
            : null;
        $cleared = 0;
        $seen = [];

        MessageDelivery::query()
            ->where('status_id', 4)
            ->whereNotNull('contact_id')
            ->when($teamId !== null, fn ($query) => $query->where('team_id', $teamId))
            ->where(function ($query): void
            {
                $query->whereNotNull('error_message')
                    ->orWhereNotNull('bounce_reason');
            })
            ->whereHas('contact', function ($query): void
            {
                $query->withoutGlobalScopes()
                    ->whereNotNull('email')
                    ->where('email', '!=', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($deliveries) use (&$cleared, &$seen, $dryRun): void
            {
                foreach ($deliveries as $delivery)
                {
                    $contactId = (int) $delivery->contact_id;
                    if (isset($seen[$contactId]))
                    {
                        continue;
                    }

                    $reason = $this->rejectionReason($delivery);
                    if ($reason === null)
                    {
                        continue;
                    }

                    $seen[$contactId] = true;
                    $cleared++;
                    if ($dryRun)
                    {
                        continue;
                    }

                    $contact = Contact::withoutGlobalScopes()->find($contactId);
                    $contact?->dropPermanentlyRejectedEmail($reason);
                }
            });

        $verb = $dryRun ? 'Would clear' : 'Cleared';
        $label = $cleared === 1 ? 'rejected email' : 'rejected emails';
        $this->info($verb.' '.$cleared.' '.$label.'.');

        return self::SUCCESS;
    }

    private function rejectionReason(MessageDelivery $delivery): ?string
    {
        foreach ([$delivery->error_message, $delivery->bounce_reason] as $reason)
        {
            if (is_string($reason) && Contact::emailFailureIsPermanent($reason))
            {
                return $reason;
            }
        }

        return null;
    }
}
