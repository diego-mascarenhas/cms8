<?php

namespace App\Sync\Contracts;

use App\Models\ExternalAccount;

interface ContactSyncProviderInterface
{
    public function sync(ExternalAccount $account): array;
}
