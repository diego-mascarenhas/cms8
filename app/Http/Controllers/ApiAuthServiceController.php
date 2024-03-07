<?php

namespace App\Http\Controllers;

use App\Services\ApiAuthService;
use Session;

class ApiAuthServiceController extends Controller
{
    public function login(ApiAuthService $apiAuthService)
    {
        $response = $apiAuthService->login(env('BRULER_USERNAME'), env('BRULER_PASSWORD'));

        if ($response->successful())
        {
            Session::put('apiToken', $response->json()['Data']['Token']);
            Session::put('apiTokenExpiry', $response->json()['Data']['TokenExpira']);

            return $response;
        }

        dd($response->json());
    }

    public function token()
    {
        $apiToken = Session::get('apiToken');

        echo $apiToken;
    }


}