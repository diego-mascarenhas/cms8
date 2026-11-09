<?php

namespace App\Support;

use Carbon\CarbonInterface;

class WebDavInboundTaskSync
{
    public static function resolveInboundStatusId(
        bool $remoteCompleted,
        ?int $currentStatusId,
        int $toDoStatusId,
        int $doneStatusId,
    ): int {
        if ($currentStatusId === null)
        {
            return $remoteCompleted ? $doneStatusId : $toDoStatusId;
        }

        if ($remoteCompleted)
        {
            return $doneStatusId;
        }

        if ((int) $currentStatusId === (int) $doneStatusId)
        {
            return $toDoStatusId;
        }

        return (int) $currentStatusId;
    }

    public static function shouldApplyRemoteContent(
        ?int $remoteUpdatedAt,
        ?CarbonInterface $localUpdatedAt,
        bool $isNewTask,
    ): bool {
        if ($isNewTask)
        {
            return true;
        }

        if ($remoteUpdatedAt === null)
        {
            return false;
        }

        if ($localUpdatedAt === null)
        {
            return true;
        }

        return $remoteUpdatedAt >= $localUpdatedAt->getTimestamp();
    }

    /**
     * VTODO reminders often have only a due date. Both columns are required on tasks.
     *
     * @return array{start_date: string, due_date: string}
     */
    public static function resolveInboundDates(?string $remoteDueDate, ?string $currentStartDate, ?string $currentDueDate): array
    {
        $dueDate = $remoteDueDate ?? $currentDueDate ?? now()->toDateString();

        return [
            'start_date' => $currentStartDate ?? $dueDate,
            'due_date' => $dueDate,
        ];
    }
}
