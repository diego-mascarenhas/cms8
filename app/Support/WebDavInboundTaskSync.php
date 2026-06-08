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
}
