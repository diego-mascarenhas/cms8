<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ApiAuthService;

class LoginApiServiceCommand extends Command
{
	protected $signature = 'api:login {usuario} {password}';
	protected $description = 'Inicia sesión en la API utilizando ApiAuthService';

	public function handle()
	{
		$usuario = $this->argument('usuario');
		$password = $this->argument('password');

		//TODO Register ApiAuthService as a service
		$service = app(ApiAuthService::class);
		$response = $service->login($usuario, $password);

		$this->info($response);
	}
}