<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Services\DomainRefreshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshDomainDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $domainId)
    {
        $this->onQueue('domain-info');
    }

    public function handle(DomainRefreshService $domainRefreshService): void
    {
        $domain = Domain::query()->find($this->domainId);

        if (! $domain)
        {
            return;
        }

        $result = $domainRefreshService->refresh($domain);

        if (! ($result['success'] ?? false))
        {
            Log::warning('Domain refresh job failed', [
                'domain_id' => $this->domainId,
                'error' => $result['error'] ?? null,
            ]);
        }
    }
}
