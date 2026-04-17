<?php

namespace App\Sync\Contracts;

use App\Models\ExternalAccount;

interface CalendarSyncProviderInterface
{
    public function sync(ExternalAccount $account): array;
}
