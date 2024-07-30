<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\Bruler\AuthService;
use App\Models\Bruler\Products;

use Log;

class BrulerFetchDataCommand extends Command
{
    protected $signature = 'fetch:bruler-data';
    protected $description = 'Fetch data from Bruler API and insert or update in products table';

    protected $authService;
    protected $showConsoleOutput;

    public function __construct(AuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
        $this->showConsoleOutput = env('SHOW_CONSOLE_OUTPUT', false);
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
                Log::info('Bruler: Authentication failed. Unable to retrieve data from the Bruler API.');
                return;
            }

            Cache::put('brulerApiToken', $brulerApiToken, now()->addHours(2));
        }

        $response = Http::withHeaders([
            'API_KEY' => env('BRULER_API_KEY'),
            'API_TOKEN' => $brulerApiToken,
            'Content-Type' => 'application/json',
        ])->post('https://brulerapi.ar/api/pedimosfacilmdw/articulos/list', [
                    'Articulo' => '',
                    'Rubro' => '', // Specific product category (EMPANADAS)
                ]);

        if ($response->successful())
        {
            $data = $response->json()['Data'];

            // Extract RemoteId from the first element
            $brulerId = $data[0]['RemoteId'] ?? null;

            if ($brulerId === null)
            {
                $this->error('RemoteId not found in the API response.');
                return;
            }

            // Check if a product with the bruler_id exists
            $product = Products::where('bruler_id', $brulerId)->first();

            // Convert current data to JSON for comparison
            $currentData = $product ? $product->data : null;

            if (json_encode($currentData) !== json_encode($data))
            {
                if ($product)
                {
                    // Update the existing product
                    $product->update([
                        'data' => $data,
                        'status' => 0,
                    ]);
                }
                else
                {
                    // Create a new product
                    Products::create([
                        'type' => 'products',
                        'bruler_id' => $brulerId,
                        'data' => $data,
                        'status' => 0,
                    ]);
                }

                if ($this->showConsoleOutput)
                {
                    $this->info("Data successfully inserted or updated in the products table.");
                }

                Log::info('Bruler: Data successfully inserted or updated in the products table.');
            }
            else
            {
                if ($this->showConsoleOutput)
                {
                    $this->info("No changes detected. Data remains the same.");
                }

                Log::info('Bruler: No changes detected. Data remains the same.');
            }
        }
        else
        {
            $this->error('Failed to retrieve data from the Bruler API.');

            Log::info('Bruler: Failed to retrieve data from the Bruler API.');
        }
    }
}
