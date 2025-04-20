<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OvhSyncDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ovh:sync-domains {--debug : Output additional debug information} {--now : Execute job immediately}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync domains from OVH services to the domains table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Syncing domains from OVH services...');
        
        $debug = $this->option('debug');
        $now = $this->option('now');
        
        $this->info('Executing synchronization' . ($debug ? ' in debug mode' : '') . ($now ? ' immediately' : ' via queue') . '...');
        
        $job = new \App\Jobs\OvhDomainSync($debug);
        
        if ($now) {
            // Execute job immediately
            $job->handle();
        } else {
            // Dispatch to queue
            dispatch($job);
        }
        
        $this->info('OVH domain sync ' . ($now ? 'completed!' : 'queued!'));
    }
} 