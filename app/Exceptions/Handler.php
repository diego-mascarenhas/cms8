<?php

namespace App\Exceptions;

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
            return redirect('/misc-not-authorized');
        }

        // Handle Authorization exceptions
        if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException)
        {
            return redirect('/misc-not-authorized');
        }

        return parent::render($request, $exception);
    }
}
