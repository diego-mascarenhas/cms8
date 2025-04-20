<?php

namespace App\Console\Commands;

use App\Jobs\OvhDomainSync as OvhDomainSyncJob;
use Illuminate\Console\Command;

class OvhDomainSync extends Command
{
    protected $signature = 'ovh:sync-domains';
    protected $description = 'Sync domains from OVH services to the domains table';

    public function handle()
    {
        $this->info('Syncing domains from OVH services...');
        
        dispatch(new OvhDomainSyncJob);
        
        $this->info('OVH domain sync job dispatched successfully!');
    }
} 