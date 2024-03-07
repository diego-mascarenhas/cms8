<?php

namespace App\Http\Controllers;

use App\Services\ApiAuthService;

class ApiAuthServiceController extends Controller
{
    public function login(ApiAuthService $apiAuthService)
    {
        $response = $apiAuthService->login('bruler_pedimosfacil', 'Bruler.PedimosFacil.2024');

        $data = $response->json();

        dd($data);
    }
}