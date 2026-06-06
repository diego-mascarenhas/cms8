<?php

namespace App\Console\Commands;

use App\Jobs\SyncMailboxEmails;
use App\Models\Mailbox;
use Illuminate\Console\Command;

class SyncMailboxesCommand extends Command
{
    protected $signature = 'mailboxes:sync
                            {--mailbox= : Sync only this mailbox ID}';

    protected $description = 'Queue sync jobs for one or all team mailboxes.';

    public function handle(): int
    {
        $mailboxId = $this->option('mailbox');

        if ($mailboxId !== null)
        {
            $mailbox = Mailbox::find($mailboxId);
            if ($mailbox === null)
            {
                $this->error("Mailbox [{$mailboxId}] not found.");

                return self::FAILURE;
            }
            $mailboxes = collect([$mailbox]);
        } else
        {
            $mailboxes = Mailbox::query()
                ->where('host', 'not like', '%.humano.local')
                ->get();
        }

        if ($mailboxes->isEmpty())
        {
            $this->warn('No mailboxes to sync.');

            return self::SUCCESS;
        }

        foreach ($mailboxes as $mailbox)
        {
            SyncMailboxEmails::dispatch($mailbox);
            $this->info("Dispatched sync for mailbox [{$mailbox->name}] (ID: {$mailbox->id}).");
        }

        return self::SUCCESS;
    }
}
