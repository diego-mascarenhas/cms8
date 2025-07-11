<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\DomainInfoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateServerDomainInfo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $serverId;

    public function __construct(?int $serverId = null)
    {
        $this->serverId = $serverId;
        $this->onQueue('domain-info');
    }

    public function handle(DomainInfoService $domainInfoService)
    {
        if ($this->serverId) {
            // Process single server
            $this->processSingleServer($this->serverId, $domainInfoService);
        } else {
            // Dispatch individual jobs for each server
            Server::select('id')->orderBy('id')->chunk(100, function ($servers) {
                foreach ($servers as $server) {
                    static::dispatch($server->id)->onQueue('domain-info');
                }
            });
        }
    }

    protected function processSingleServer(int $serverId, DomainInfoService $domainInfoService)
    {
        if ($server = Server::find($serverId)) {
            $domainInfo = $domainInfoService->getDomainInfo($server->server_url);

            $server->update([
                'data' => $domainInfo,
            ]);
        }
    }
}
