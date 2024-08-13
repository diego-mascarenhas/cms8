<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\Bruler\AuthService;

class BrulerSendDataCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'bruler:send-data';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Send data to Bruler API';

	protected $authService;
	protected $showConsoleOutput;

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct(AuthService $authService)
	{
		parent::__construct();
		$this->authService = $authService;
		$this->showConsoleOutput = env('SHOW_CONSOLE_OUTPUT', false);
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function handle()
	{
		// Retrieve the Bruler API token from cache
		$brulerApiToken = Cache::get('brulerApiToken');

		if (!$brulerApiToken)
		{
			$this->warn('No API token found. Attempting to authenticate...');
			$brulerApiToken = $this->authService->authenticate(env('BRULER_USERNAME'), env('BRULER_PASSWORD'));

			if (!$brulerApiToken)
			{
				$this->error('Authentication failed. Unable to send data to the Bruler API.');
				return;
			}

			// Store the new token in cache
			Cache::put('brulerApiToken', $brulerApiToken, now()->addHours(2));
		}

		// Make the request to the Bruler API
		$response = Http::withHeaders([
			'API_KEY' => env('BRULER_API_KEY'),
			'API_TOKEN' => $brulerApiToken,
			'Content-Type' => 'application/json',
		])->post('https://brulerapi.ar/api/pedimosfacilmdw/comando/1/add/1', [
					'Command' => 1,
					'Quantity' => 4,
					'Options' => 'Table for 4',
					'IsDevelopment' => true,
				]);

		// Handle the response
		if ($response->successful())
		{
			$data = $response->json()['Data'];

			// Convert the data to a JSON string for display
			if ($this->showConsoleOutput)
            {
				$dataString = json_encode($data, JSON_PRETTY_PRINT);
				$this->info("Data successfully sent. Response data: \n$dataString");
			}
		}
		else
		{
			$this->error('Failed to send data to the Bruler API.');
		}
	}
}
