<?php

namespace App\Jobs;

use App\Models\Mailbox;
use App\Services\Imap\MailboxConnectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
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
            Log::warning('Mailbox sync skipped: IMAP connection failed', [
                'mailbox_id' => $this->mailbox->id,
                'mailbox_name' => $this->mailbox->name,
                'host' => $this->mailbox->host,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
