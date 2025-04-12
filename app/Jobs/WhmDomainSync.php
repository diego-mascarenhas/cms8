<?php

namespace App\Jobs;

use App\Services\WhmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WhmDomainSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('whm-sync');
    }

    public function handle(WhmService $whmService)
    {
        $whmService->syncDomainsFromAllServers();
    }
} 