<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

class HomeController extends Controller
{
    public function index(): RedirectResponse
    {
        if (auth()->check())
        {
            return redirect()->route('dashboard');
        }

        $routeName = config('app.public_home_route');
        if (filled($routeName) && Route::has($routeName))
        {
            return redirect()->route($routeName);
        }

        $path = $this->validatedPublicHomePath(config('app.public_home_path'));
        if ($path !== null)
        {
            return redirect($path);
        }

        return redirect()->route('login');
    }

    /**
     * @return non-empty-string|null
     */
    private function validatedPublicHomePath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '')
        {
            return null;
        }
        $normalized = '/'.ltrim($path, '/');
        if (str_contains($normalized, '//') || preg_match('/[\?\#\\\\\x00-\x1F]/', $normalized))
        {
            return null;
        }
        if ($normalized !== '/' && ! preg_match('#^/[A-Za-z0-9/._\-]+$#', $normalized))
        {
            return null;
        }

        return $normalized;
    }
}
