<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ApplyProdReadDatabaseWhenEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isLocal() || ! config('app.allow_prod_read_toggle'))
        {
            return $next($request);
        }

        if (! $request->session()->get('use_prod_read_database'))
        {
            return $next($request);
        }

        if (! config('app.prod_read_credentials_configured'))
        {
            return $next($request);
        }

        $previousDefault = config('database.default');

        if ($previousDefault === 'prod_read')
        {
            return $next($request);
        }

        Config::set('database.default', 'prod_read');
        DB::purge($previousDefault);
        DB::purge('prod_read');

        View::share('usingProdReadDatabase', true);

        return $next($request);
    }
}
