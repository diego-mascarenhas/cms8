<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VCenterService;
use App\Models\Host;
use Illuminate\Support\Facades\Log;

class UpdateVMMetrics extends Command
{
    protected $signature = 'update:vm-metrics';
    protected $description = 'Updates VM metrics from vCenter';

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
                $this->info('Starting VM metrics update...');
            }

            $vms = $this->vCenterService->getVMs();

            foreach ($vms['value'] as $vmData)
            {
                Host::updateOrCreate(
                    ['host' => $vmData['vm']],
                    [
                        'name' => $vmData['name'] ?? 'N/A',
                        'power_state' => $vmData['power_state'] ?? 'N/A',
                        'memory_size_MiB' => $vmData['memory_size_MiB'] ?? 0,
                        'cpu_count' => $vmData['cpu_count'] ?? 0,
                        'type_id' => 2,
                    ]
                );

                if ($this->showConsoleOutput)
                {
                    $this->info("Updated metrics for VM: {$vmData['name']} - {$vmData['vm']}");
                }
            }

            if ($this->showConsoleOutput)
            {
                $this->info('VM metrics updated successfully.');
            }
        }
        catch (\Exception $e)
        {
            Log::error('Error updating VM metrics: ' . $e->getMessage());

            if ($this->showConsoleOutput)
            {
                $this->error('Error updating VM metrics: ' . $e->getMessage());
            }
        }
    }
}
