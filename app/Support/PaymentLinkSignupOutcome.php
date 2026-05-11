<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class PaymentLinkSignupOutcome
{
    private function __construct(
        public readonly ?User $user,
        public readonly ?RedirectResponse $redirect,
        public readonly bool $isNewUser,
    ) {}

    public static function redirectTo(RedirectResponse $response): self
    {
        return new self(null, $response, false);
    }

    public static function login(User $user, bool $isNewUser): self
    {
        return new self($user, null, $isNewUser);
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }
}
