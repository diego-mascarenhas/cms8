<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\FortifyLoginResponse;
use App\Http\Responses\FortifyTwoFactorLoginResponse;
use App\Http\Responses\RegistrationRegisterResponse;
use App\Support\PendingTeamInvitation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RegisterResponse::class, RegistrationRegisterResponse::class);
        $this->app->singleton(LoginResponse::class, FortifyLoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponse::class, FortifyTwoFactorLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::registerView(function ()
        {
            $invitation = PendingTeamInvitation::get(request());

            return view('auth.register', [
                'teamInvitation' => $invitation,
                'lockInvitationEmail' => $invitation !== null,
            ]);
        });

        RateLimiter::for('login', function (Request $request)
        {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request)
        {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
