<?php

namespace App\Services\Bruler;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AuthService
{
    protected $authUrl = 'https://brulerapi.ar/api/authmdw/login/';

    public function authenticate($username, $password)
    {
        $response = Http::withHeaders([
            'API_KEY' => env('BRULER_API_KEY'),
            'Content-Type' => 'application/json',
        ])->post($this->authUrl, [
                    'usuario' => $username,
                    'password' => $password,
                ]);

        if ($response->successful())
        {
            $tokenData = $response->json()['Data'];
            Session::put('brulerApiToken', $tokenData['Token']);
            Session::put('brulerApiTokenExpiry', $tokenData['TokenExpira']);
            Cache::put('brulerApiToken', $tokenData['Token'], 120);

            Log::info(print_r($tokenData, true));

            return $tokenData['Token'];
        }

        return null;
    }
}
