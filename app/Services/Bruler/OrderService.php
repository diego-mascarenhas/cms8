<?php

namespace App\Services\Bruler;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class OrderService
{
    protected $baseUrl = 'https://brulerapi.ar/api/pedimosfacilmdw/ordenes/add';
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function createOrder($data)
    {
        if (!Session::has('brulerApiToken') || $this->tokenIsExpired()) {
            $this->authService->authenticate(env('BRULER_USER'), env('BRULER_PASSWORD'));
        }

        $brulerApiToken = Session::get('brulerApiToken');

        $response = Http::withHeaders([
            'API_KEY' => env('BRULER_API_KEY'),
            'API_TOKEN' => $brulerApiToken,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl, $data);

        return $response->body();
    }

    protected function tokenIsExpired()
    {
        $expiry = Session::get('brulerApiTokenExpiry');
        return now()->gte($expiry);
    }
}
