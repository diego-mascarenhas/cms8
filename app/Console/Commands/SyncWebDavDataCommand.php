<?php

namespace App\Console\Commands;

use App\Enums\ExternalProvider;
use App\Jobs\SyncWebDavDataJob;
use App\Models\ExternalAccount;
use Illuminate\Console\Command;

class SyncWebDavDataCommand extends Command
{
    protected $signature = 'webdav:sync-data {--account_id=}';

    protected $description = 'Queue WebDAV contacts, calendar and task synchronization jobs';

    public function handle(): int
    {
        $accountId = $this->option('account_id');

        $query = ExternalAccount::query()
            ->where('provider', ExternalProvider::WebDav);

        if ($accountId !== null)
        {
            $query->where('id', (int) $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty())
        {
            $this->warn('No WebDAV external accounts found.');

            return self::SUCCESS;
        }

        foreach ($accounts as $account)
        {
            SyncWebDavDataJob::dispatch($account->id);
        }

        $this->info('Queued sync for '.$accounts->count().' WebDAV accounts.');

        return self::SUCCESS;
    }
}
