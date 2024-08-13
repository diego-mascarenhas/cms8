<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WHMService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateWHMServiceStatus extends Command
{
    protected $signature = 'update:whm-service-status';
    protected $description = 'Updates WHM service statuses from multiple servers';

    protected $whmService;
    protected $showConsoleOutput;

    public function __construct(WHMService $whmService)
    {
        parent::__construct();
        $this->whmService = $whmService;
        $this->showConsoleOutput = env('SHOW_CONSOLE_OUTPUT', false);
    }

    public function handle()
    {
        try
        {
            if ($this->showConsoleOutput)
            {
                $this->info('Starting WHM service status update...');
            }

            $statuses = $this->whmService->getServiceStatuses();

            // if ($this->showConsoleOutput)
            // {
            //     $this->info('Statuses obtained from WHM servers: ' . print_r($statuses, true));
            // }

            foreach ($statuses as $server => $serverData)
            {
                foreach ($serverData['acct'] as $status)
                {
                    if (isset($status['user']))
                    {
                        $user = $status['user'];

                        $service = DB::table('services')->where('data->user', $user)->first();

                        if ($service)
                        {
                            $serviceData = json_decode($service->data, true);

                            foreach ($status as $key => $value)
                            {
                                $serviceData[$key] = $value;
                            }

                            $updatedServiceData = json_encode($serviceData);

                            DB::table('services')->where('data->user', $user)->update([
                                'data' => $updatedServiceData,
                                'updated_at' => Carbon::now(),
                            ]);

                            if ($this->showConsoleOutput)
                            {
                                $this->info("Updated plan for user: $user");
                            }
                        }
                        else
                        {
                            if ($this->showConsoleOutput)
                            {
                                $this->warn("Service not found for user: $user");
                            }
                        }
                    }
                    else
                    {
                        if ($this->showConsoleOutput)
                        {
                            $this->warn('Invalid status data: ' . print_r($status, true));
                        }
                    }
                }
            }

            if ($this->showConsoleOutput)
            {
                $this->info('WHM service statuses updated successfully.');
            }
        }
        catch (\Exception $e)
        {
            Log::error('Error updating WHM service statuses: ' . $e->getMessage());

            if ($this->showConsoleOutput)
            {
                $this->error('Error updating WHM service statuses: ' . $e->getMessage());
            }
        }
    }
}
