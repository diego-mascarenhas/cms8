<?php

namespace App\Http\Controllers;

use App\Models\MessageDelivery;
use Illuminate\Http\Request;

class MessageTrackingController extends Controller
{
	// Tracking de apertura
	public function track($token)
	{
		\Log::info('Tracking: token recibido', ['token' => $token]);
		$delivery = \App\Models\MessageDelivery::all()->first(function ($d) use ($token)
		{
			return hash_equals($d->getTrackingToken(), $token);
		});
		if ($delivery)
		{
			\Log::info('Tracking: delivery encontrado', ['id' => $delivery->id]);
			if (! $delivery->opened_at)
			{
				// Create tracking event using the new method
				\App\Models\MessageDeliveryTracking::createEvent(
					$delivery->id,
					'opened',
					[
						'source' => 'email_tracking_pixel',
						'timestamp' => now(),
					],
				);

				$delivery->markAsOpened();
			}
		} else
		{
			\Log::info('Tracking: delivery NO encontrado para token', ['token' => $token]);
		}
		// Devolver imagen transparente
		$img = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');

		return response($img)->header('Content-Type', 'image/gif');
	}

	// Tracking de click
	public function trackClick(Request $request, $token)
	{
		$delivery = MessageDelivery::all()->first(function ($d) use ($token)
		{
			return hash_equals($d->getTrackingToken(), $token);
		});

		if ($delivery)
		{
			$originalUrl = $request->query('url', '/');

			// If this is the first interaction and open tracking is enabled,
			// also register an open event (user must have opened the email to click)
			if (!$delivery->opened_at && $delivery->message && $delivery->message->enable_open_tracking) {
				\App\Models\MessageDeliveryTracking::createEvent(
					$delivery->id,
					'opened',
					[
						'source' => 'inferred_from_click',
						'timestamp' => now(),
					],
				);
				$delivery->markAsOpened();
				\Log::info('Open event inferred from click', [
					'delivery_id' => $delivery->id,
					'contact_email' => $delivery->contact ? $delivery->contact->email : 'unknown',
					'clicked_url' => $originalUrl,
				]);
			}

			// Create tracking event using the new method
			\App\Models\MessageDeliveryTracking::createEvent(
				$delivery->id,
				'clicked',
				[
					'source' => 'email_link_click',
					'original_url' => $originalUrl,
					'timestamp' => now(),
				],
			);

			// Update or create the link tracking record
			$this->updateLinkClickCount($delivery, $originalUrl);

			        // Update contact status to "Conversión" (ID 3) when they click any link
        // But don't change status if they are already a client (status_id 5)
        if ($delivery->contact && $delivery->contact->status_id != 3 && $delivery->contact->status_id != 5) {
            $delivery->contact->update(['status_id' => 3]);
            \Log::info('Contact status updated to Conversión', [
                'contact_id' => $delivery->contact->id,
                'contact_email' => $delivery->contact->email,
                'delivery_id' => $delivery->id,
                'clicked_url' => $originalUrl,
                'previous_status' => $delivery->contact->getOriginal('status_id'),
            ]);
        } elseif ($delivery->contact && $delivery->contact->status_id == 5) {
            \Log::info('Contact is already a client - status not changed', [
                'contact_id' => $delivery->contact->id,
                'contact_email' => $delivery->contact->email,
                'delivery_id' => $delivery->id,
                'clicked_url' => $originalUrl,
                'current_status' => 5,
            ]);
        }

			$delivery->markAsClicked();
		}

		$url = $request->query('url', '/');
		return redirect($url);
	}

	/**
	 * Update click count for a specific link
	 */
	private function updateLinkClickCount(MessageDelivery $delivery, string $url)
	{
		$link = \App\Models\MessageDeliveryLink::where('message_delivery_id', $delivery->id)
			->where('link', $url)
			->first();

		if ($link) {
			// Increment click count and update timestamp
			$link->increment('click_count');
			$link->touch(); // Updates updated_at timestamp

			\Log::info('Link click count updated', [
				'delivery_id' => $delivery->id,
				'url' => $url,
				'click_count' => $link->click_count,
				'first_click' => $link->created_at,
				'last_click' => $link->updated_at,
			]);
		} else {
			// This shouldn't happen if EmailTrackingHelper is working correctly,
			// but let's handle it just in case
			\App\Models\MessageDeliveryLink::create([
				'message_delivery_id' => $delivery->id,
				'link' => $url,
				'click_count' => 1,
				'created_at' => now(),
				'updated_at' => now(),
			]);

			\Log::info('Link tracking record created on click', [
				'delivery_id' => $delivery->id,
				'url' => $url,
			]);
		}
	}
}
