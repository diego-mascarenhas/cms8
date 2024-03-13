<?php

namespace App\Console\Commands;

use Cache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\BrulerData;
use Session;

class FetchBrulerDataCommand extends Command
{
    protected $signature = 'fetch:bruler-data';
    protected $description = 'Fetch data from Bruler API and insert into bruler_data table';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $apiToken = Cache::get('apiToken');

        $response = Http::withHeaders([
            'API_KEY' => 'F2PVc01qDR97hna24Lt8Bw611pfGe258',
            'API_TOKEN' => $apiToken,
            'Content-Type' => 'application/json',
        ])->post('https://brulerapi.ar/api/pedimosfacilmdw/articulos/list', [
                    'Articulo' => '',
                    'Rubro' => 'EMPANADAS',
                ]);

        if ($response->successful())
        {
            $data = $response->json()['Data'];

            BrulerData::create([
                'type' => 'products',
                'data' => $data,
            ]);

            $this->info('Datos insertados exitosamente en la tabla bruler_data.');
        }
        else
        {
            $this->error('Error al obtener datos de la API de Bruler.');
        }
    }
}