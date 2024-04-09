<?php

namespace App\Console\Commands;

use Cache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\BrulerData;
use Session;

class SendBrulerDataCommand extends Command
{
	protected $signature = 'send:bruler-data';
	protected $description = 'Send data to Bruler API';

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
		])->post('https://brulerapi.ar/api/pedimosfacilmdw/comando/1/add/1', [
					'Comando' => 1,
					'Cantidad' => 4,
					'Opcionales' => 'Mesa para 4',
					'IsDevelopment' => true,
				]);

		if ($response->successful())
		{
			$data = $response->json()['Data'];

			$this->info('Datos enviados correctamente.');
		}
		else
		{
			$this->error('Error al enviar los datos a la API de Bruler.');
		}
	}
}