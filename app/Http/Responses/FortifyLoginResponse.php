<?php

namespace App\Http\Responses;

use App\Support\AuthIntendedUrlGuard;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson())
        {
            return new JsonResponse(['two_factor' => false]);
        }

        $default = Fortify::redirects('login');
        $intended = session()->pull('url.intended', $default);
        $target = AuthIntendedUrlGuard::sanitizeIntendedUrl($intended, $default);

        return redirect()->to($target);
    }
}
