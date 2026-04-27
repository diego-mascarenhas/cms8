<?php

namespace App\Console\Commands;

use App\Enums\ExternalProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectGoogleSyncCommand extends Command
{
    protected $signature = 'google:inspect-sync {--team_id=} {--limit=200}';

    protected $description = 'Inspect Google sync status for all teams/accounts';

    public function handle(): int
    {
        $teamId = $this->option('team_id');
        $limit = max(1, (int) $this->option('limit'));

        $query = DB::table('external_accounts as ea')
            ->join('teams as t', 't.id', '=', 'ea.team_id')
            ->join('users as u', 'u.id', '=', 'ea.user_id')
            ->leftJoin('contact_sync_mappings as csm', 'csm.external_account_id', '=', 'ea.id')
            ->leftJoin('calendar_event_sync_mappings as cesm', 'cesm.external_account_id', '=', 'ea.id')
            ->where('ea.provider', ExternalProvider::Google->value)
            ->groupBy('ea.id', 'ea.team_id', 't.name', 'ea.user_id', 'u.email', 'ea.last_synced_at')
            ->select([
                'ea.id as account_id',
                'ea.team_id',
                't.name as team_name',
                'ea.user_id',
                'u.email as user_email',
                'ea.last_synced_at',
                DB::raw('COUNT(DISTINCT csm.id) as contacts_mapped'),
                DB::raw('COUNT(DISTINCT cesm.id) as events_mapped'),
            ])
            ->orderBy('ea.team_id')
            ->orderBy('ea.id')
            ->limit($limit);

        if ($teamId !== null)
        {
            $query->where('ea.team_id', (int) $teamId);
        }

        $rows = $query->get();

        if ($rows->isEmpty())
        {
            $this->warn('No Google external accounts found for the selected scope.');

            return self::SUCCESS;
        }

        $latestRuns = DB::table('sync_runs as sr')
            ->joinSub(
                DB::table('sync_runs')
                    ->select('external_account_id', 'resource', DB::raw('MAX(id) as latest_id'))
                    ->groupBy('external_account_id', 'resource'),
                'latest',
                function ($join): void
                {
                    $join->on('latest.latest_id', '=', 'sr.id');
                },
            )
            ->select([
                'sr.external_account_id',
                'sr.resource',
                'sr.status',
                'sr.pulled_count',
                'sr.upserted_count',
                'sr.deleted_count',
                'sr.error_message',
                'sr.finished_at',
            ])
            ->get()
            ->groupBy('external_account_id');

        $outputRows = $rows->map(function ($row) use ($latestRuns): array
        {
            $runByResource = collect($latestRuns->get($row->account_id, []))->keyBy('resource');
            $contactsRun = $runByResource->get('contacts');
            $calendarRun = $runByResource->get('calendar_events');

            $contactsStatus = $contactsRun
                ? "{$contactsRun->status} (upserted: {$contactsRun->upserted_count}, deleted: {$contactsRun->deleted_count})"
                : 'n/a';

            $calendarStatus = $calendarRun
                ? "{$calendarRun->status} (upserted: {$calendarRun->upserted_count}, deleted: {$calendarRun->deleted_count})"
                : 'n/a';

            $lastError = $calendarRun->error_message ?? $contactsRun->error_message ?? '';

            return [
                'account_id' => (string) $row->account_id,
                'team_id' => (string) $row->team_id,
                'team' => (string) $row->team_name,
                'user' => (string) $row->user_email,
                'contacts_map' => (string) $row->contacts_mapped,
                'events_map' => (string) $row->events_mapped,
                'last_synced' => $row->last_synced_at ? (string) $row->last_synced_at : '-',
                'contacts_run' => $contactsStatus,
                'calendar_run' => $calendarStatus,
                'error' => $lastError !== '' ? mb_strimwidth((string) $lastError, 0, 80, '...') : '-',
            ];
        })->toArray();

        $this->table([
            'account_id',
            'team_id',
            'team',
            'user',
            'contacts_map',
            'events_map',
            'last_synced',
            'contacts_run',
            'calendar_run',
            'error',
        ], $outputRows);

        $this->newLine();
        $this->info('Accounts shown: '.count($outputRows));
        $this->line('Tip: use --team_id=ID to filter one team.');

        return self::SUCCESS;
    }
}
