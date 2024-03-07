<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Log;

class ApiAuthService
{
    protected $authUrl = 'https://brulerapi.ar/api/authmdw/login/';

    public function login($usuario, $password)
    {
        $response = Http::withHeaders([
            'API_KEY' => env('BRULER_API_KEY'),
            'Content-Type' => 'application/json',
        ])->post($this->authUrl, [
                    'usuario' => $usuario,
                    'password' => $password,
                ]);

        if ($response->successful())
        {
            Session::put('apiToken', $response->json()['Data']['Token']);
            Session::put('apiTokenExpiry', $response->json()['Data']['TokenExpira']);

            Log::info(print_r($response->json()['Data'], true));

            return $response;
        }

        return null;
    }
}