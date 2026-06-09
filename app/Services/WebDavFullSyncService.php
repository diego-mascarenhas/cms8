<?php

namespace App\Services;

use App\Jobs\PushCalendarEventToWebDavJob;
use App\Jobs\PushContactToWebDavJob;
use App\Jobs\PushTaskToWebDavJob;
use App\Jobs\SyncWebDavDataJob;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\ExternalAccount;
use App\Models\Task;
use App\Models\Team;

class WebDavFullSyncService
{
    public function __construct(
        private readonly WebDavIntegrationService $webDavIntegrationService,
    ) {}

    /**
     * @return array{
     *     outbound: array{contacts: int, calendar: int, tasks: int},
     *     inbound: array{contacts: bool, calendar: bool, tasks: bool}
     * }
     */
    public function queueFullSyncForTeam(Team $team): array
    {
        $account = $this->webDavIntegrationService->webDavAccountForTeam($team);

        if ($account === null)
        {
            throw new \RuntimeException(__('app.webdav_sync_all_no_account'));
        }

        $outbound = $this->queueOutboundForTeam($team);
        $inbound = $this->queueInboundForAccount($account, $team);

        if (
            $outbound['contacts'] === 0
            && $outbound['calendar'] === 0
            && $outbound['tasks'] === 0
            && ! $inbound['contacts']
            && ! $inbound['calendar']
            && ! $inbound['tasks']
        ) {
            throw new \RuntimeException(__('app.webdav_sync_all_nothing_enabled'));
        }

        return [
            'outbound' => $outbound,
            'inbound' => $inbound,
        ];
    }

    /**
     * @return array{contacts: int, calendar: int, tasks: int}
     */
    public function queueOutboundForTeam(Team $team): array
    {
        $counts = [
            'contacts' => 0,
            'calendar' => 0,
            'tasks' => 0,
        ];

        if ($team->webdavContactsOutboundSyncEnabled())
        {
            Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->chunkById(200, function ($contacts) use (&$counts): void
                {
                    foreach ($contacts as $contact)
                    {
                        PushContactToWebDavJob::dispatch($contact->id);
                        $counts['contacts']++;
                    }
                });
        }

        if ($team->webdavCalendarOutboundSyncEnabled())
        {
            CalendarEvent::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->chunkById(200, function ($events) use (&$counts): void
                {
                    foreach ($events as $event)
                    {
                        PushCalendarEventToWebDavJob::dispatch($event->id, 'updated');
                        $counts['calendar']++;
                    }
                });
        }

        if ($team->webdavTasksOutboundSyncEnabled())
        {
            Task::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->chunkById(200, function ($tasks) use (&$counts): void
                {
                    foreach ($tasks as $task)
                    {
                        PushTaskToWebDavJob::dispatch($task->id);
                        $counts['tasks']++;
                    }
                });
        }

        return $counts;
    }

    /**
     * @return array{contacts: bool, calendar: bool, tasks: bool}
     */
    public function queueInboundForAccount(ExternalAccount $account, Team $team): array
    {
        $inbound = [
            'contacts' => $team->webdavContactsInboundSyncEnabled(),
            'calendar' => $team->webdavCalendarInboundSyncEnabled(),
            'tasks' => $team->webdavTasksInboundSyncEnabled(),
        ];

        if ($inbound['contacts'] || $inbound['calendar'] || $inbound['tasks'])
        {
            SyncWebDavDataJob::dispatch($account->id);
        }

        return $inbound;
    }
}
