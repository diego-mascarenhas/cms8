<?php

namespace App\Http\Controllers;

use App\Services\ApiAuthService;

class ApiAuthServiceController extends Controller
{
  public function login(ApiAuthService $apiAuthService)
  {
    $response = $apiAuthService->login(env('BRULER_USERNAME'), env('BRULER_PASSWORD'));

    $data = $response->json();

    dd($data);
  }
}