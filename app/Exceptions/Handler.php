<?php

namespace App\Exceptions;

use App\Support\AuthIntendedUrlGuard;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e)
        {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        // Handle 404 errors
        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException || $exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException)
        {
            return redirect()->route('404');
        }

        // Handle 403 Unauthorized/Forbidden errors
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $exception->getStatusCode() === 403)
        {
            return $this->redirectNotAuthorized($exception->getMessage());
        }

        // Handle Authorization exceptions
        if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException)
        {
            return $this->redirectNotAuthorized($exception->getMessage());
        }

        return parent::render($request, $exception);
    }

    protected function redirectNotAuthorized(?string $message = null)
    {
        $redirect = redirect('/misc-not-authorized');
        $message = is_string($message) ? trim($message) : '';

        if ($message !== '' && ! in_array(strtolower($message), ['forbidden', 'unauthorized', 'this action is unauthorized.'], true))
        {
            $redirect->with('unauthorized_message', $message);
        }

        return $redirect;
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if (AuthIntendedUrlGuard::shouldSkipStoringIntended($request))
        {
            return $this->shouldReturnJson($request, $exception)
                ? response()->json(['message' => $exception->getMessage()], 401)
                : redirect()->route('login');
        }

        return parent::unauthenticated($request, $exception);
    }
}
