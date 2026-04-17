<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncGoogleDataJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $externalAccountId)
    {
    }

    public function handle(): void
    {
        SyncGoogleContactsJob::dispatch($this->externalAccountId);
        SyncGoogleCalendarEventsJob::dispatch($this->externalAccountId);
    }
}
