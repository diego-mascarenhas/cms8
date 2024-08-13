<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegisterApplication extends Command
{
    protected $signature = 'app:register-application';
    protected $description = 'Register the application with the central server';
    protected $showConsoleOutput;

    public function __construct()
    {
        parent::__construct();
        $this->showConsoleOutput = env('SHOW_CONSOLE_OUTPUT', false);
    }

    public function handle()
    {
        $appName = env('APP_NAME');
        $appEnv = env('APP_ENV');
        $appKey = env('APP_KEY');
        $appDebug = env('APP_DEBUG') ? 'true' : 'false';
        $appUrl = env('APP_URL');
        $appCommit = env('APP_VERSION');

        if ($this->showConsoleOutput)
        {
            $this->info("Registering application {$appName}...");
        }

        $response = Http::post('https://cms8.revisionalpha.es/api/register-application', [
            'name' => $appName,
            'env' => $appEnv,
            'key' => $appKey,
            'debug' => $appDebug,
            'url' => $appUrl,
            'commit' => $appCommit,
        ]);

        if ($response->failed())
        {
            $errorMessage = 'Application registration failed.';
            $errorData = [
                'status' => $response->status(),
                'response' => $response->body(),
            ];

            Log::error($errorMessage, $errorData);

            if ($this->showConsoleOutput)
            {
                $this->error($errorMessage);
                $this->error(print_r($errorData, true));
            }
        }
        else
        {
            $successMessage = 'Application registered successfully.';

            if ($this->showConsoleOutput)
            {
                $this->info($successMessage);
            }
        }
    }
}