<?php

namespace App\Console\Commands;

use App\Enums\ExternalProvider;
use App\Jobs\SyncGoogleDataJob;
use App\Models\ExternalAccount;
use Illuminate\Console\Command;

class SyncGoogleDataCommand extends Command
{
    protected $signature = 'google:sync-data {--account_id=}';

    protected $description = 'Queue Google contacts and calendar synchronization jobs';

    public function handle(): int
    {
        $accountId = $this->option('account_id');

        $query = ExternalAccount::query()
            ->where('provider', ExternalProvider::Google);

        if ($accountId !== null)
        {
            $query->where('id', (int) $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty())
        {
            $this->warn('No Google external accounts found.');

            return self::SUCCESS;
        }

        foreach ($accounts as $account)
        {
            SyncGoogleDataJob::dispatch($account->id);
        }

        $this->info('Queued sync for '.$accounts->count().' Google accounts.');

        return self::SUCCESS;
    }
}
