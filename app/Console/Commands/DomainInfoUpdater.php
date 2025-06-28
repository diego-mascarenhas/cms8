<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;

class DomainInfoUpdater extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:update {--domain=} {--mock-php}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update domain information (WordPress detection and PHP version)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domainId = $this->option('domain');
        $mockPhp = $this->option('mock-php');

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
        $this->info("Processing {$count} domains...");

        $wpDetected = 0;
        $phpUpdated = 0;

        foreach ($domains as $domain) {
            $this->info("Processing domain: {$domain->domain} (ID: {$domain->id})");
            $this->info('Current settings: site_type = ' . ($domain->site_type ?: 'NULL') . ', php_version = ' . ($domain->php_version ?: 'NULL'));

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

            // Update PHP version
            $oldVersion = $domain->php_version;

            if ($mockPhp) {
                $mockVersion = '8.2'; // Use PHP 8.2 for the test
                $this->info("Mocking PHP version update to {$mockVersion}");
                $domain->testUpdatePhpVersion($mockVersion);
            } else {
                $this->info('Fetching real PHP version from server...');
                $phpFromServer = $domain->getPhpVersionFromServer();
                $this->info('Server reported PHP version: ' . ($phpFromServer ?: 'NOT DETECTED'));
                $domain->updatePhpVersion();
            }

            if ($oldVersion !== $domain->php_version) {
                $phpUpdated++;
                $this->info("Updated php_version from {$oldVersion} to {$domain->php_version}");
            }

            $this->info("Final settings: site_type = {$domain->site_type}, php_version = {$domain->php_version}");
            $this->newLine();
        }

        $this->info('Task completed!');
        $this->info("WordPress detected: {$wpDetected} domains");
        $this->info("PHP version updated: {$phpUpdated} domains");
    }
}
