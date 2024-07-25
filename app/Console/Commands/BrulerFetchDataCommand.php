<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\Bruler\AuthService;
use App\Models\Bruler\Products;

class BrulerFetchDataCommand extends Command
{
    protected $signature = 'fetch:bruler-data';
    protected $description = 'Fetch data from Bruler API and insert into order_slips table';

    protected $authService;
    protected $showConsoleOutput;

    public function __construct(AuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    public function handle()
    {
        $brulerApiToken = Cache::get('brulerApiToken');

        if (!$brulerApiToken)
        {
            $this->warn('No API token found. Attempting to authenticate...');
            $brulerApiToken = $this->authService->authenticate(env('BRULER_USERNAME'), env('BRULER_PASSWORD'));

            if (!$brulerApiToken)
            {
                $this->error('Authentication failed. Unable to retrieve data from the Bruler API.');
                return;
            }

            // Store the new token in cache
            Cache::put('brulerApiToken', $brulerApiToken, now()->addHours(2));
        }

        $response = Http::withHeaders([
            'API_KEY' => env('BRULER_API_KEY'),
            'API_TOKEN' => $brulerApiToken,
            'Content-Type' => 'application/json',
        ])->post('https://brulerapi.ar/api/pedimosfacilmdw/articulos/list', [
                    'Articulo' => '',
                    'Rubro' => '', // EMPANADAS
                ]);

        if ($response->successful())
        {
            $data = $response->json()['Data'];

            Products::create([
                'type' => 'products',
                'data' => $data,
                'status' => 0,
            ]);

            // Convert data to JSON for better readability in the console output
            if ($this->showConsoleOutput)
            {
                $dataString = json_encode($data, JSON_PRETTY_PRINT);
                $this->info("Data successfully inserted into the order_slips table. Response data: \n$dataString");
            }
        }
        else
        {
            $this->error('Failed to retrieve data from the Bruler API.');
        }
    }
}
