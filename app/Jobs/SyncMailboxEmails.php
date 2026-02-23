<?php

namespace App\Jobs;

use App\Models\Mailbox;
use App\Services\Imap\MailboxConnectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class SyncMailboxEmails implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Mailbox $mailbox,
        public ?int $limit = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MailboxConnectionService $service): void
    {
        try
        {
            $service->syncMessages($this->mailbox, $this->limit);
        } catch (ConnectionFailedException $e)
        {
            report($e);
            throw $e;
        }
    }
}
