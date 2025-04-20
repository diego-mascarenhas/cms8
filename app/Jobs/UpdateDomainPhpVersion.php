<?php

namespace App\Jobs;

use App\Models\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateDomainPhpVersion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 2;
    
    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;
    
    /**
     * The domain ID to process.
     *
     * @var int
     */
    protected $domainId;
    
    /**
     * Optional mock version for testing.
     *
     * @var string|null
     */
    protected $mockVersion;

    /**
     * Create a new job instance.
     *
     * @param int $domainId
     * @return void
     */
    public function __construct(int $domainId, ?string $mockVersion = null)
    {
        $this->domainId = $domainId;
        $this->mockVersion = $mockVersion;
        $this->onQueue('domain-version');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $domain = Domain::find($this->domainId);
            
            if (!$domain) {
                Log::warning("Domain not found for ID: {$this->domainId}");
                return;
            }
            
            $oldVersion = $domain->php_version;
            Log::info("Current PHP version for {$domain->domain}: " . ($oldVersion ?: 'NULL'));
            
            if ($this->mockVersion) {
                Log::info("Using mock PHP version: {$this->mockVersion}");
                $domain->testUpdatePhpVersion($this->mockVersion);
            } else {
                Log::info("Fetching PHP version from WHM for {$domain->domain}...");
                $phpFromServer = $domain->getPhpVersionFromServer();
                Log::info("WHM reported PHP version: " . ($phpFromServer ?: 'NOT DETECTED'));
                $domain->updatePhpVersion();
            }
            
            if ($oldVersion !== $domain->php_version) {
                Log::info("Updated PHP version for {$domain->domain} from {$oldVersion} to {$domain->php_version}");
            } else {
                Log::info("PHP version for {$domain->domain} remains unchanged: {$domain->php_version}");
            }
        } catch (\Exception $e) {
            Log::error("Error updating PHP version for domain ID {$this->domainId}: " . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
} 