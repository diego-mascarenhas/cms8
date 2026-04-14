<?php

namespace App\Http\Responses;

use App\Enums\RegistrationMode;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class RegistrationRegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson())
        {
            return new JsonResponse('', 201);
        }

        return match (RegistrationMode::fromConfiguration())
        {
            RegistrationMode::Checkout => redirect()->route('registration.checkout.start'),
            RegistrationMode::Gate => redirect()->intended(Fortify::redirects('register', '/')),
            RegistrationMode::Free => redirect()->intended(Fortify::redirects('register', '/')),
        };
    }
}
