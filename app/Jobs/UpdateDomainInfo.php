<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Services\DomainInfoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateDomainInfo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $domainId;

    public function __construct(?int $domainId = null)
    {
        $this->domainId = $domainId;
        $this->onQueue('domain-info');
    }

    public function handle(DomainInfoService $domainInfoService)
    {
        if ($this->domainId) {
            // Process single domain
            $this->processSingleDomain($this->domainId, $domainInfoService);
        } else {
            // Dispatch individual jobs for each domain
            Domain::select('id', 'domain')
                ->orderBy('id')
                ->chunk(100, function ($domains) {
                    foreach ($domains as $domain) {
                        static::dispatch($domain->id)->onQueue('domain-info');
                    }
                });
        }
    }

    protected function processSingleDomain(int $domainId, DomainInfoService $domainInfoService)
    {
        if ($domain = Domain::find($domainId)) {
            $domainInfo = $domainInfoService->getDomainInfo($domain->domain);
            
            $domain->update([
                'data' => $domainInfo
            ]);
        }
    }
} 