<?php

namespace App\Observers;

use App\Jobs\PushTaskToWebDavJob;
use App\Models\Task;
use App\Models\Team;

class TaskWebDavOutboundObserver
{
    public function saved(Task $task): void
    {
        $this->dispatchWhenEnabled($task);
    }

    public function deleted(Task $task): void
    {
        $this->dispatchWhenEnabled($task);
    }

    public function restored(Task $task): void
    {
        $this->dispatchWhenEnabled($task);
    }

    private function dispatchWhenEnabled(Task $task): void
    {
        $team = Team::query()->find($task->team_id);

        if ($team === null || ! $team->webdavTasksOutboundSyncEnabled())
        {
            return;
        }

        PushTaskToWebDavJob::dispatch($task->id);
    }
}
