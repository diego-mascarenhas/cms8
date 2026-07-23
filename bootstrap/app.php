<?php

use App\Http\Middleware\ApplyProdReadDatabaseWhenEnabled;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsurePasswordsUnlocked;
use App\Http\Middleware\EnsureRegistrationBillingComplete;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\ModifyMenuBasedOnRole;
use App\Http\Middleware\PrepareDemoPresentation;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\TeamTokenAuth;
use App\Http\Middleware\TrackContactViewing;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateSignature;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware)
    {
        // Global middleware
        $middleware->use([
            // \App\Http\Middleware\TrustHosts::class,
            TrustProxies::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            PreventRequestsDuringMaintenance::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);

        // Web middleware group
        $middleware->web(append: [
            LocaleMiddleware::class,
            ModifyMenuBasedOnRole::class,
            TrackContactViewing::class,
            EnsureRegistrationBillingComplete::class,
            ApplyProdReadDatabaseWhenEnabled::class,
            PrepareDemoPresentation::class,
        ]);

        // API middleware group
        $middleware->api(prepend: [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'auth' => Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => RedirectIfAuthenticated::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            'signed' => ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'role' => RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'team.token' => TeamTokenAuth::class,
            'passwords.unlocked' => EnsurePasswordsUnlocked::class,
        ]);

        // Encrypt cookies
        $middleware->encryptCookies(except: [
            // Add any cookies that should not be encrypted
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions)
    {
        $redirectNotAuthorized = function (?string $message = null)
        {
            $redirect = redirect('/misc-not-authorized');
            $message = is_string($message) ? trim($message) : '';

            if ($message !== '' && ! in_array(strtolower($message), ['forbidden', 'unauthorized', 'this action is unauthorized.'], true))
            {
                $redirect->with('unauthorized_message', $message);
            }

            return $redirect;
        };

        $exceptions->render(function (HttpException $e, $request) use ($redirectNotAuthorized)
        {
            if ($e->getStatusCode() !== 403 || $request->expectsJson())
            {
                return null;
            }

            return $redirectNotAuthorized($e->getMessage());
        });

        $exceptions->render(function (AuthorizationException $e, $request) use ($redirectNotAuthorized)
        {
            if ($request->expectsJson())
            {
                return null;
            }

            return $redirectNotAuthorized($e->getMessage());
        });
    })->create();
