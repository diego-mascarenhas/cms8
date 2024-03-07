<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class ApiService
{
    protected $baseUrl = 'https://brulerapi.ar/api/pedimosfacilmdw/ordenes/add';
    protected $apiAuthService;

    public function __construct(ApiAuthService $apiAuthService)
    {
        $this->apiAuthService = $apiAuthService;
    }

    public function addOrder($data)
    {
        if (!Session::has('apiToken') || $this->isTokenExpired())
        {
            $this->apiAuthService->login(env('BRULER_USER'), env('BRULER_PASSWORD'));
        }

        $apiToken = Session::get('apiToken');

        $response = Http::withHeaders([
            'API_KEY' => env('BRULER_API_KEY'),
            'API_TOKEN' => $apiToken,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl, $data);

        return $response->body();
    }

    protected function isTokenExpired()
    {
        $expiry = Session::get('apiTokenExpiry');
        return now()->gte($expiry);
    }
}