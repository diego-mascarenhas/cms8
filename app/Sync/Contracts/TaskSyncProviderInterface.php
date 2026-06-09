<?php

namespace App\Sync\Contracts;

use App\Models\ExternalAccount;

interface TaskSyncProviderInterface
{
    public function sync(ExternalAccount $account): array;
}
