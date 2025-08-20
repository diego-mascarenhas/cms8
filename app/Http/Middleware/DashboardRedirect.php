<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DashboardRedirect
{
	/**
	 * Handle an incoming request.
	 *
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next)
	{
		// Only intercept the main dashboard route
		if ($request->is('dashboard') || $request->is('dashboard/analytics'))
		{
			// Check if a specific dashboard type is defined in the .env
			$dashboardType = env('APP_DASHBOARD_TYPE');

			if ($dashboardType)
			{
				// Redirect to the appropriate dashboard based on the type
				switch ($dashboardType)
				{
					case 'collaborator':
						return redirect()->route('dashboard.collaborator');
					case 'client':
						return redirect()->route('dashboard.client');
					case 'project':
						return redirect()->route('dashboard.project');
						// Default case: proceed with the request
				}
			}
		}

		return $next($request);
	}
}
