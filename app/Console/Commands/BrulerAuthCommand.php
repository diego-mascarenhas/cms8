<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Bruler\AuthService;

class BrulerAuthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:bruler-auth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Authenticate and fetch the token from Bruler API';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $username = env('BRULER_USERNAME');
        $password = env('BRULER_PASSWORD');

        $authService = app(AuthService::class);
        $token = $authService->authenticate($username, $password);

        if ($token)
        {
            $this->info("Authentication successful. Token: {$token}");
        }
        else
        {
            $this->error('Authentication failed. Please check your credentials.');
        }
    }
}
