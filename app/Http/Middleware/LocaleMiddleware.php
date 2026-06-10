<?php

namespace App\Http\Middleware;

use App\Support\ApplicationLocales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('locale'))
        {
            $locale = ApplicationLocales::normalize(session()->get('locale'));

            if (ApplicationLocales::isSupported($locale))
            {
                app()->setLocale($locale);
                $this->applyTranslatorFallback($locale);
            }
        }

        return $next($request);
    }

    private function applyTranslatorFallback(string $locale): void
    {
        $fallback = $locale === ApplicationLocales::ARGENTINA
            ? ApplicationLocales::DEFAULT
            : (string) config('app.fallback_locale');

        app('translator')->setFallback($fallback);
    }
}
