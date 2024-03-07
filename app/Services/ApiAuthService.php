<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApiAuthService
{
    public function login($usuario, $password)
    {
        $response = Http::withHeaders([
            'API_KEY' => 'F2PVc01qDR97hna24Lt8Bw611pfGe258',
            'Content-Type' => 'application/json'
        ])->post('https://brulerapi.ar/api/authmdw/login/', [
                    'usuario' => $usuario,
                    'password' => $password
                ]);

        return $response;
    }
}