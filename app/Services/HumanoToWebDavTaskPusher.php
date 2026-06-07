<?php

namespace App\Services;

use App\Models\ExternalAccount;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskSyncMapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HumanoToWebDavTaskPusher
{
    public function __construct(
        private readonly WebDavApiClient $webDavApiClient,
        private readonly WebDavIntegrationService $webDavIntegrationService,
        private readonly WebDavTeamExternalAccountResolver $accountResolver,
    ) {}

    public function sync(Task $task): void
    {
        if ($task->trashed())
        {
            $this->deleteRemoteCopies($task);

            return;
        }

        $account = $this->accountResolver->firstWebDavAccountForTeam((int) $task->team_id);

        if ($account === null)
        {
            return;
        }

        $email = $this->webDavIntegrationService->davEmail($account);
        $mapping = TaskSyncMapping::query()
            ->where('external_account_id', $account->id)
            ->where('task_id', $task->id)
            ->first();

        $doneStatusId = TaskStatus::query()->where('name', 'DONE')->value('id');
        $payload = [
            'summary' => (string) ($task->title ?: 'Task'),
            'description' => $task->description,
            'due_at' => $task->due_date?->toIso8601String(),
            'completed' => (int) $task->status_id === (int) $doneStatusId,
        ];

        try
        {
            if ($mapping === null)
            {
                $uid = (string) Str::uuid();
                $result = $this->webDavApiClient->upsertTask($email, array_merge($payload, ['uid' => $uid]));
                $externalId = (string) ($result['uid'] ?? $uid);

                TaskSyncMapping::query()->create([
                    'external_account_id' => $account->id,
                    'task_id' => $task->id,
                    'external_id' => $externalId,
                    'last_synced_at' => now(),
                ]);

                return;
            }

            $this->webDavApiClient->upsertTask($email, $payload, (string) $mapping->external_id);
            $mapping->forceFill(['last_synced_at' => now()])->save();
        } catch (\Throwable $exception)
        {
            Log::warning('HumanoToWebDavTaskPusher failed.', [
                'task_id' => $task->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function deleteRemoteCopies(Task $task): void
    {
        $mappings = TaskSyncMapping::query()->where('task_id', $task->id)->get();

        foreach ($mappings as $mapping)
        {
            $account = ExternalAccount::query()->find($mapping->external_account_id);

            if ($account === null)
            {
                continue;
            }

            try
            {
                $email = $this->webDavIntegrationService->davEmail($account);
                $this->webDavApiClient->deleteTask($email, (string) $mapping->external_id);
                $mapping->delete();
            } catch (\Throwable $exception)
            {
                Log::warning('HumanoToWebDavTaskPusher delete failed.', [
                    'task_id' => $task->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
