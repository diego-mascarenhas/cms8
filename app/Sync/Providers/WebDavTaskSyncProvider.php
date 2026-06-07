<?php

namespace App\Sync\Providers;

use App\Enums\SyncResource;
use App\Models\ExternalAccount;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskSyncMapping;
use App\Services\WebDavApiClient;
use App\Services\WebDavIntegrationService;
use App\Support\TeamTaskBoardResolver;
use App\Sync\Contracts\TaskSyncProviderInterface;
use Carbon\Carbon;

class WebDavTaskSyncProvider implements TaskSyncProviderInterface
{
    public function __construct(
        private readonly WebDavIntegrationService $webDavIntegrationService,
        private readonly WebDavApiClient $webDavApiClient,
    ) {}

    public function sync(ExternalAccount $account): array
    {
        $email = $this->webDavIntegrationService->davEmail($account);
        $items = $this->webDavApiClient->listTasks($email);

        $pulled = 0;
        $upserted = 0;
        $boardId = TeamTaskBoardResolver::resolveBoardId((int) $account->team_id);
        $toDoStatusId = TaskStatus::query()->where('name', 'TO_DO')->value('id');
        $doneStatusId = TaskStatus::query()->where('name', 'DONE')->value('id');

        foreach ($items as $item)
        {
            $pulled++;
            $externalId = (string) ($item['uid'] ?? '');

            if ($externalId === '')
            {
                continue;
            }

            $mapping = TaskSyncMapping::query()
                ->where('external_account_id', $account->id)
                ->where('external_id', $externalId)
                ->first();

            $task = $mapping?->task;

            if ($task === null)
            {
                $task = new Task;
                $task->team_id = $account->team_id;
                $task->board_id = $boardId;
                $task->responsible_id = $account->user_id;
            }

            $task->title = (string) ($item['summary'] ?? 'WebDAV Task');
            $task->description = $item['description'] ?? null;
            $task->due_date = isset($item['due_at']) ? Carbon::parse($item['due_at'])->toDateString() : null;
            $task->status_id = ! empty($item['completed']) ? $doneStatusId : $toDoStatusId;
            $task->saveQuietly();

            TaskSyncMapping::query()->updateOrCreate(
                [
                    'external_account_id' => $account->id,
                    'external_id' => $externalId,
                ],
                [
                    'task_id' => $task->id,
                    'last_synced_at' => now(),
                ],
            );

            $upserted++;
        }

        return [
            'pulled_count' => $pulled,
            'upserted_count' => $upserted,
            'deleted_count' => 0,
            'resource' => SyncResource::Tasks->value,
        ];
    }
}
