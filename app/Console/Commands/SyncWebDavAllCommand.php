<?php

namespace App\Console\Commands;

use App\Models\ExternalAccount;
use App\Models\Team;
use App\Services\WebDavFullSyncService;
use Illuminate\Console\Command;

class SyncWebDavAllCommand extends Command
{
    protected $signature = 'webdav:sync-all {--team_id=} {--account_id=} {--outbound-only : Only push Humano data to WebDAV}';

    protected $description = 'Queue full WebDAV sync (outbound push and inbound pull) for a team';

    public function handle(WebDavFullSyncService $syncService): int
    {
        $teamId = $this->option('team_id');
        $accountId = $this->option('account_id');

        if ($teamId === null && $accountId === null)
        {
            $this->error('Provide --team_id or --account_id.');

            return self::FAILURE;
        }

        if ($accountId !== null)
        {
            $account = ExternalAccount::query()->with('team')->find((int) $accountId);

            if ($account === null || $account->team === null)
            {
                $this->error('WebDAV external account not found.');

                return self::FAILURE;
            }

            $team = $account->team;
        } else
        {
            $team = Team::query()->find((int) $teamId);

            if ($team === null)
            {
                $this->error('Team not found.');

                return self::FAILURE;
            }
        }

        try
        {
            if ($this->option('outbound-only'))
            {
                $outbound = $syncService->queueOutboundForTeam($team);

                if ($outbound['contacts'] === 0 && $outbound['calendar'] === 0 && $outbound['tasks'] === 0)
                {
                    throw new \RuntimeException(__('app.webdav_sync_all_nothing_enabled'));
                }

                $this->info(sprintf(
                    'Queued WebDAV outbound push for team #%d: %d contacts, %d calendar events, %d tasks.',
                    $team->id,
                    $outbound['contacts'],
                    $outbound['calendar'],
                    $outbound['tasks'],
                ));

                return self::SUCCESS;
            }

            $result = $syncService->queueFullSyncForTeam($team);
        } catch (\Throwable $exception)
        {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $outbound = $result['outbound'];
        $inbound = $result['inbound'];

        $this->info(sprintf(
            'Queued WebDAV full sync for team #%d. Outbound: %d contacts, %d events, %d tasks. Inbound: %s.',
            $team->id,
            $outbound['contacts'],
            $outbound['calendar'],
            $outbound['tasks'],
            implode(', ', array_keys(array_filter($inbound))),
        ));

        return self::SUCCESS;
    }
}
