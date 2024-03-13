<?php

namespace App\Services;

use Cache;
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
            $token = $response->json()['Data']['Token'];

            Session::put('apiToken', $token);
            Session::put('apiTokenExpiry', $response->json()['Data']['TokenExpira']);
            Cache::put('apiToken', $token, 120);

            Log::info(print_r($response->json()['Data'], true));

            return $token;
        }

        return null;
    }
}