<?php

namespace App\Http\Responses;

use App\Support\AuthIntendedUrlGuard;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyTwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson())
        {
            return new JsonResponse('', 204);
        }

        $default = Fortify::redirects('login');
        $intended = session()->pull('url.intended', $default);
        $target = AuthIntendedUrlGuard::sanitizeIntendedUrl($intended, $default);

        return redirect()->to($target);
    }
}
