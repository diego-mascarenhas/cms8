<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\Team;
use App\Services\HumanoToWebDavTaskPusher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PushTaskToWebDavJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $taskId) {}

    public function handle(HumanoToWebDavTaskPusher $pusher): void
    {
        $task = Task::withTrashed()->find($this->taskId);

        if ($task === null)
        {
            return;
        }

        $team = Team::query()->find($task->team_id);

        if ($team === null || ! $team->webdavTasksOutboundSyncEnabled())
        {
            return;
        }

        try
        {
            $pusher->sync($task);
        } catch (\Throwable $exception)
        {
            Log::warning('PushTaskToWebDavJob failed (non-fatal).', [
                'task_id' => $this->taskId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
