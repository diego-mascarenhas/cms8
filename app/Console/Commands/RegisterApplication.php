<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegisterApplication extends Command
{
    protected $signature = 'app:register-application';
    protected $description = 'Register the application with the central server';

    public function handle()
    {
        $response = Http::post('https://cms8.revisionalpha.es/api/register-application', [
            'name' => env('APP_NAME'),
            'env' => env('APP_ENV'),
            'key' => env('APP_KEY'),
            'debug' => env('APP_DEBUG'),
            'url' => env('APP_URL'),
        ]);

        if ($response->failed())
        {
            Log::error('Application registration failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        }
        else
        {
            Log::info('Application registered successfully', [
                'response' => $response->body(),
            ]);
        }
    }
}
