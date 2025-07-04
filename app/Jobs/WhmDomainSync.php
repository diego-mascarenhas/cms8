<?php

namespace App\Jobs;

use App\Services\WHMService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WhmDomainSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('whm-sync');
    }

    public function handle(WHMService $whmService)
    {
        try {
            $result = $whmService->syncDomainsFromAllServers();

            if (! $result['success'] && empty($result['successful_servers'])) {
                Log::error('Critical error in WHM domain sync: No servers were processed successfully', $result);
            }
        } catch (\Exception $e) {
            Log::error('Critical error in WHM domain sync: '.$e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }
}
