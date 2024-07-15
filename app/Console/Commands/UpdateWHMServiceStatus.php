<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WHMService;
use Illuminate\Support\Facades\Log;

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
            
            if ($this->showConsoleOutput)
            {
                $this->info('Statuses obtained from WHM servers: ' . print_r($statuses, true));
            }

            // Aquí puedes agregar la lógica para actualizar tu base de datos o realizar acciones necesarias con los datos obtenidos

            if ($this->showConsoleOutput)
            {
                $this->info('WHM service statuses updated successfully.');
            }
        }
        catch (\Exception $e)
        {
            Log::error('Error updating WHM service statuses: ' . $e->getMessage());
        }
    }
}
