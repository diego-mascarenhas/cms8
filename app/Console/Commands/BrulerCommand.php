<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ApiAuthService;

class BrulerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:bruler';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch data from Bruler API';

    public function handle()
    {
        $usuario = env('BRULER_USERNAME');
        $password = env('BRULER_PASSWORD');

        $service = app(ApiAuthService::class);
        $response = $service->login($usuario, $password);

        $this->info($response);
    }
}