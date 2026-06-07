<?php

namespace App\Jobs;

use App\Models\ExternalAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncWebDavDataJob implements ShouldQueue
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

        if ($account->team->webdavContactsInboundSyncEnabled())
        {
            SyncWebDavContactsJob::dispatch($this->externalAccountId);
        }

        if ($account->team->webdavCalendarInboundSyncEnabled())
        {
            SyncWebDavCalendarEventsJob::dispatch($this->externalAccountId);
        }

        if ($account->team->webdavTasksInboundSyncEnabled())
        {
            SyncWebDavTasksJob::dispatch($this->externalAccountId);
        }
    }
}
