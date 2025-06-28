<?php

namespace App\Console\Commands;

use App\Jobs\UpdateDomainSiteType as UpdateDomainSiteTypeJob;
use App\Models\Domain;
use Illuminate\Console\Command;

class UpdateDomainSiteType extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:update-site-type {--domain=} {--queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect and update domain site type (WordPress detection)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domainId = $this->option('domain');
        $useQueue = $this->option('queue');

        if ($useQueue) {
            $this->processViaQueue($domainId);
        } else {
            $this->processDirectly($domainId);
        }
    }

    /**
     * Process domains directly without using a queue
     */
    protected function processDirectly($domainId)
    {
        $query = Domain::query();

        if ($domainId) {
            // Check if domain is numeric (ID) or string (domain name)
            if (is_numeric($domainId)) {
                $query->where('id', $domainId);
            } else {
                $query->where('domain', $domainId);
            }
        }

        $domains = $query->get();

        $count = $domains->count();
        $this->info("Processing {$count} domains directly...");

        $wpDetected = 0;

        foreach ($domains as $domain) {
            $this->info("Processing domain: {$domain->domain} (ID: {$domain->id})");

            // Test if domain is WordPress
            $isWordPress = $domain->isWordPress();
            $this->info('WordPress detection result: ' . ($isWordPress ? 'YES' : 'NO'));

            // Update WordPress status
            $wasWp = $domain->site_type === 'WordPress';
            $domain->updateSiteType();
            $isWp = $domain->site_type === 'WordPress';

            if (! $wasWp && $isWp) {
                $wpDetected++;
                $this->info('Updated site_type to WordPress');
            }

            $this->info("Final site_type: {$domain->site_type}");
        }

        $this->info('Task completed!');
        $this->info("WordPress detected: {$wpDetected} domains");
    }

    /**
     * Process domains using a queue job
     */
    protected function processViaQueue($domainId)
    {
        if ($domainId) {
            if (is_numeric($domainId)) {
                // Process single domain by ID
                $domain = Domain::find($domainId);
                if ($domain) {
                    $this->info("Dispatching job for domain: {$domain->domain} (ID: {$domain->id})");
                    UpdateDomainSiteTypeJob::dispatch($domain->id);
                } else {
                    $this->error("Domain with ID {$domainId} not found");
                }
            } else {
                // Process single domain by domain name
                $domain = Domain::where('domain', $domainId)->first();
                if ($domain) {
                    $this->info("Dispatching job for domain: {$domain->domain} (ID: {$domain->id})");
                    UpdateDomainSiteTypeJob::dispatch($domain->id);
                } else {
                    $this->error("Domain {$domainId} not found");
                }
            }

            $this->info('Job dispatched successfully!');
        } else {
            // Process all domains in chunks
            $this->info('Dispatching jobs for all domains...');
            $count = Domain::count();
            $this->info("Total domains: {$count}");

            Domain::select('id', 'domain')
                ->orderBy('id')
                ->chunk(50, function ($domains) {
                    foreach ($domains as $domain) {
                        $this->info("Dispatching job for domain: {$domain->domain} (ID: {$domain->id})");
                        UpdateDomainSiteTypeJob::dispatch($domain->id);
                    }
                });

            $this->info('All jobs dispatched successfully!');
        }
    }
}
