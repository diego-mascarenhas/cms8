<?php

namespace App\Http\Middleware;

use App\Models\UserContactAction;
use App\Traits\TracksContactActions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrackContactViewing
{
	use TracksContactActions;

	protected $excludedRoutes = [
		'contact.search',
		'contact.end-action',
	];

	public function handle(Request $request, Closure $next)
	{
		$currentRoute = $request->route()->getName();

		// if (app()->environment('local'))
		// {
		// 	Log::info('TrackContactViewing middleware executing', [
		// 		'route' => $currentRoute,
		// 		'session_tracking_id' => session('tracking_id'),
		// 		'session_contact_id' => session('viewing_contact_id'),
		// 		'previous_url' => session('previous_url'),
		// 		'current_url' => $request->fullUrl(),
		// 		'time' => now(),
		// 	]);
		// }

		if ($currentRoute === 'contact.show')
		{
			session(['previous_url' => $request->fullUrl()]);
		} elseif (session('previous_url') && strpos(session('previous_url'), '/contact/') !== false)
		{
			$tracking = UserContactAction::find(session('tracking_id'));

			if ($tracking && ! $tracking->end_time && ! $request->ajax())
			{
				// if (app()->environment('local'))
				// {
				// 	Log::info('Ending tracking', [
				// 		'tracking_id' => session('tracking_id'),
				// 		'contact_id' => session('viewing_contact_id'),
				// 		'from_url' => session('previous_url'),
				// 		'to_url' => $request->fullUrl(),
				// 		'time' => now(),
				// 	]);
				// }

				$this->endActionTracking(session('tracking_id'));
				session()->forget(['tracking_id', 'viewing_contact_id', 'previous_url']);
			}
		}

		$response = $next($request);

		return $response;
	}
}
