<?php

namespace App\Jobs;

use App\Models\ExternalAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncGoogleDataJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $externalAccountId) {}

    public function handle(): void
    {
        $account = ExternalAccount::query()->with('team')->find($this->externalAccountId);

        if ($account === null || $account->team === null)
        {
            return;
        }

        if ($account->team->googleContactsInboundSyncEnabled())
        {
            SyncGoogleContactsJob::dispatch($this->externalAccountId);
        }

        if ($account->team->googleCalendarInboundSyncEnabled())
        {
            SyncGoogleCalendarEventsJob::dispatch($this->externalAccountId);
        }
    }
}
