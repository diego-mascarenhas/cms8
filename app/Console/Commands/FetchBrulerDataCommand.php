<?php

namespace App\Console\Commands;

use Cache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\OrderSlip;
use Session;

class FetchBrulerDataCommand extends Command
{
    protected $signature = 'fetch:bruler-data';
    protected $description = 'Fetch data from Bruler API and insert into order_slips table';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $apiToken = Cache::get('apiToken');

        $response = Http::withHeaders([
            'API_KEY' => env('BRULER_API_KEY'),
            'API_TOKEN' => $apiToken,
            'Content-Type' => 'application/json',
        ])->post('https://brulerapi.ar/api/pedimosfacilmdw/articulos/list', [
                    'Articulo' => '',
                    'Rubro' => 'EMPANADAS',
                ]);

        if ($response->successful())
        {
            $data = $response->json()['Data'];

            OrderSlip::create([
                'type' => 'products',
                'data' => $data,
            ]);

            $this->info('Datos insertados exitosamente en la tabla order_slips.');
        }
        else
        {
            $this->error('Error al obtener datos de la API de Bruler.');
        }
    }
}
