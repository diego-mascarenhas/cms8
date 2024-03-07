<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApiAuthService
{
  public function login($usuario, $password)
  {
    $response = Http::withHeaders([
      'API_KEY' => env('BRULER_API_KEY'),
      'Content-Type' => 'application/json'
    ])->post('https://brulerapi.ar/api/authmdw/login/', [
          'usuario' => $usuario,
          'password' => $password
        ]);

    return $response;
  }
}