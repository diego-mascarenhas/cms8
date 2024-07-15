<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VCenterService;
use App\Models\Host;
use Illuminate\Support\Facades\Log;

class UpdateHostMetrics extends Command
{
    protected $signature = 'update:host-metrics';
    protected $description = 'Updates host metrics from vCenter';

    protected $vCenterService;
    protected $showConsoleOutput;

    public function __construct(VCenterService $vCenterService)
    {
        parent::__construct();
        $this->vCenterService = $vCenterService;
        $this->showConsoleOutput = env('SHOW_CONSOLE_OUTPUT', false);
    }

    public function handle()
    {
        try
        {
            if ($this->showConsoleOutput)
            {
                $this->info('Starting host metrics update...');
            }

            $hosts = $this->vCenterService->getHosts();

            // if ($this->showConsoleOutput)
            // {
            //     $this->info('Hosts obtained from vCenter: ' . print_r($hosts, true));
            // }

            foreach ($hosts['value'] as $hostData)
            {
                Host::updateOrCreate(
                    ['host' => $hostData['host']],
                    [
                        'name' => $hostData['name'] ?? 'N/A',
                        'power_state' => $hostData['power_state'] ?? 'N/A',
                        'connection_state' => $hostData['connection_state'] ?? 'N/A'
                    ]
                );

                if ($this->showConsoleOutput)
                {
                    $this->info("Updated metrics for host: {$hostData['name']} - {$hostData['host']}");
                }
            }

            if ($this->showConsoleOutput)
            {
                $this->info('Host metrics updated successfully.');
            }
        }
        catch (\Exception $e)
        {
            Log::error('Error updating host metrics: ' . $e->getMessage());

            if ($this->showConsoleOutput)
            {
                $this->error('Error updating host metrics: ' . $e->getMessage());
            }
        }
    }
}
