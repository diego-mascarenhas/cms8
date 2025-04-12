<?php

namespace App\Console\Commands;

use App\Jobs\WhmDomainSync as WhmDomainSyncJob;
use Illuminate\Console\Command;

class WhmDomainSync extends Command
{
    protected $signature = 'whm:sync-domains';
    protected $description = 'Sync domains from all WHM servers';

    public function handle()
    {
        $this->info('Syncing domains from WHM servers...');
        
        dispatch(new WhmDomainSyncJob);
        
        $this->info('Domain sync job dispatched successfully!');
    }
} 