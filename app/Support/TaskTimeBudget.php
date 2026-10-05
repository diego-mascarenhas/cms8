<?php

namespace App\Support;

use App\Models\Task;
use App\Models\Time;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TaskTimeBudget
{
    public static function estimatedSeconds(Task $task): ?int
    {
        if ($task->estimated_hours === null || $task->estimated_hours === '')
        {
            return null;
        }

        $hours = (float) $task->estimated_hours;

        if ($hours <= 0)
        {
            return 0;
        }

        return (int) round($hours * 3600);
    }

    public static function closedSeconds(int $taskId): int
    {
        return (int) Time::withoutGlobalScope('team')
            ->where('task_id', $taskId)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get()
            ->sum(function (Time $time): int
            {
                return max(0, $time->end_time->getTimestamp() - $time->start_time->getTimestamp());
            });
    }

    /**
     * Seconds still available before the estimate, counting open timers up to now.
     */
    public static function remainingSeconds(Task $task): ?int
    {
        $estimated = self::estimatedSeconds($task);

        if ($estimated === null)
        {
            return null;
        }

        $open = 0;
        $now = now()->getTimestamp();

        foreach (self::openTimers($task->id) as $time)
        {
            $open += max(0, $now - $time->start_time->getTimestamp());
        }

        return max(0, $estimated - self::closedSeconds($task->id) - $open);
    }

    /**
     * Moment when the open timers together reach the estimate.
     */
    public static function exhaustionAt(Task $task): ?Carbon
    {
        $estimated = self::estimatedSeconds($task);

        if ($estimated === null)
        {
            return null;
        }

        $open = self::openTimers($task->id);

        if ($open->isEmpty())
        {
            return self::closedSeconds($task->id) >= $estimated ? now() : null;
        }

        $sumStarts = $open->sum(fn (Time $time): int => $time->start_time->getTimestamp());
        $timestamp = (int) floor(($estimated - self::closedSeconds($task->id) + $sumStarts) / $open->count());

        return Carbon::createFromTimestamp($timestamp);
    }

    /**
     * Close open timers so the task total does not pass the estimate.
     */
    public static function cap(Task $task): int
    {
        $exhaustion = self::exhaustionAt($task);

        if (! $exhaustion instanceof CarbonInterface || $exhaustion->greaterThan(now()))
        {
            return 0;
        }

        $stopped = 0;

        foreach (self::openTimers($task->id) as $time)
        {
            $end = $exhaustion->greaterThan($time->start_time)
                ? $exhaustion->copy()
                : $time->start_time->copy();

            $time->end_time = $end;
            $time->calculateDuration();
            $stopped++;
        }

        return $stopped;
    }

    public static function capOverdue(): int
    {
        $taskIds = Time::withoutGlobalScope('team')
            ->whereNull('end_time')
            ->whereNotNull('task_id')
            ->distinct()
            ->pluck('task_id');

        $stopped = 0;

        foreach ($taskIds as $taskId)
        {
            $task = Task::withoutGlobalScopes()->find($taskId);

            if ($task)
            {
                $stopped += self::cap($task);
            }
        }

        return $stopped;
    }

    /**
     * People who logged time. Anyone with an open timer is listed first.
     *
     * @param  Collection<int, Time>  $times
     * @return list<array{id: int, name: string, working: bool}>
     */
    public static function workers(Collection $times): array
    {
        $people = [];

        foreach ($times->groupBy('user_id') as $entries)
        {
            $user = $entries->first()?->user;
            $name = $user?->name;

            if (! $user || ! is_string($name) || $name === '')
            {
                continue;
            }

            $working = $entries->contains(function (Time $time): bool
            {
                return $time->end_time === null && (bool) $time->start_time;
            });

            $people[] = [
                'id' => (int) $user->id,
                'name' => $name,
                'working' => $working,
            ];
        }

        usort($people, function (array $left, array $right): int
        {
            if ($left['working'] !== $right['working'])
            {
                return $left['working'] ? -1 : 1;
            }

            return strcasecmp($left['name'], $right['name']);
        });

        return $people;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Time>
     */
    private static function openTimers(int $taskId)
    {
        return Time::withoutGlobalScope('team')
            ->where('task_id', $taskId)
            ->whereNull('end_time')
            ->whereNotNull('start_time')
            ->orderBy('start_time')
            ->get();
    }
}
