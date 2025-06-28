<?php

namespace App\Console\Commands;

use App\Jobs\UpdateDomainPhpVersion as UpdateDomainPhpVersionJob;
use App\Models\Domain;
use Illuminate\Console\Command;

class UpdateDomainPhpVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:update-php-version {--domain=} {--queue} {--mock=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect and update domain PHP version from WHM';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domainId = $this->option('domain');
        $useQueue = $this->option('queue');
        $mockVersion = $this->option('mock');

        if ($useQueue) {
            $this->processViaQueue($domainId, $mockVersion);
        } else {
            $this->processDirectly($domainId, $mockVersion);
        }
    }

    /**
     * Process domains directly without using a queue
     */
    protected function processDirectly($domainId, $mockVersion)
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

        $phpUpdated = 0;

        foreach ($domains as $domain) {
            $this->info("Processing domain: {$domain->domain} (ID: {$domain->id})");
            $this->info('Current PHP version: ' . ($domain->php_version ?: 'NULL'));

            $oldVersion = $domain->php_version;

            if ($mockVersion) {
                $this->info("Mocking PHP version to: {$mockVersion}");
                $domain->testUpdatePhpVersion($mockVersion);
            } else {
                $this->info('Fetching PHP version from server...');
                $phpFromServer = $domain->getPhpVersionFromServer();
                $this->info('Detected PHP version: ' . ($phpFromServer ?: 'NOT DETECTED'));
                $domain->updatePhpVersion();
            }

            if ($oldVersion !== $domain->php_version) {
                $phpUpdated++;
                $this->info("Updated PHP version from {$oldVersion} to {$domain->php_version}");
            } else {
                $this->info("PHP version unchanged: {$domain->php_version}");
            }
        }

        $this->info('Task completed!');
        $this->info("PHP versions updated: {$phpUpdated} domains");
    }

    /**
     * Process domains using a queue job
     */
    protected function processViaQueue($domainId, $mockVersion)
    {
        if ($domainId) {
            if (is_numeric($domainId)) {
                // Process single domain by ID
                $domain = Domain::find($domainId);
                if ($domain) {
                    $this->info("Dispatching job for domain: {$domain->domain} (ID: {$domain->id})");
                    UpdateDomainPhpVersionJob::dispatch($domain->id, $mockVersion);
                } else {
                    $this->error("Domain with ID {$domainId} not found");
                }
            } else {
                // Process single domain by domain name
                $domain = Domain::where('domain', $domainId)->first();
                if ($domain) {
                    $this->info("Dispatching job for domain: {$domain->domain} (ID: {$domain->id})");
                    UpdateDomainPhpVersionJob::dispatch($domain->id, $mockVersion);
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
                ->chunk(50, function ($domains) use ($mockVersion) {
                    foreach ($domains as $domain) {
                        $this->info("Dispatching job for domain: {$domain->domain} (ID: {$domain->id})");
                        UpdateDomainPhpVersionJob::dispatch($domain->id, $mockVersion);
                    }
                });

            $this->info('All jobs dispatched successfully!');
        }
    }
}
