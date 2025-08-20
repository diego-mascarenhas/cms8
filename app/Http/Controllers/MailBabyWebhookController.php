<?php

namespace App\Http\Controllers;

use App\Models\MessageDelivery;
use App\Services\MailBabyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MailBabyWebhookController extends Controller
{
	private $mailBabyService;

	public function __construct(MailBabyService $mailBabyService)
	{
		$this->mailBabyService = $mailBabyService;
	}

	/**
	 * Handle MailBaby webhook notifications
	 */
	public function handle(Request $request)
	{
		try
		{
			$payload = $request->getContent();
			$signature = $request->header('X-MailBaby-Signature');

			// Validate webhook signature if available
			if ($signature && ! $this->mailBabyService->validateWebhookSignature($payload, $signature))
			{
				Log::warning('MailBaby webhook: Invalid signature', [
					'signature' => $signature,
					'ip' => $request->ip(),
				]);

				return response('Invalid signature', 401);
			}

			$data = $request->json()->all();

			Log::info('MailBaby webhook received', [
				'data' => $data,
				'ip' => $request->ip(),
			]);

			// Process different webhook events
			$eventType = $data['event'] ?? $data['type'] ?? 'unknown';
			$mailbabyId = $data['id'] ?? $data['message_id'] ?? null;

			if (! $mailbabyId)
			{
				Log::warning('MailBaby webhook: No message ID provided', $data);

				return response('No message ID', 400);
			}

			// Find the message delivery by provider message ID
			$delivery = MessageDelivery::where('provider_message_id', $mailbabyId)
				->where('email_provider', 'mailbaby')
				->first();

			if (! $delivery)
			{
				Log::warning('MailBaby webhook: Message delivery not found', [
					'mailbaby_id' => $mailbabyId,
					'event' => $eventType,
				]);

				return response('Message not found', 404);
			}

			// Process different event types
			switch ($eventType)
			{
				case 'delivered':
				case 'delivery':
					$this->handleDelivered($delivery, $data);
					break;

				case 'bounced':
				case 'bounce':
					$this->handleBounced($delivery, $data);
					break;

				case 'opened':
				case 'open':
					$this->handleOpened($delivery, $data);
					break;

				case 'clicked':
				case 'click':
					$this->handleClicked($delivery, $data);
					break;

				case 'failed':
				case 'error':
					$this->handleFailed($delivery, $data);
					break;

				default:
					Log::info('MailBaby webhook: Unknown event type', [
						'event' => $eventType,
						'data' => $data,
					]);
					break;
			}

			return response('OK', 200);
		} catch (\Exception $e)
		{
			Log::error('MailBaby webhook: Exception processing webhook', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'payload' => $request->getContent(),
			]);

			return response('Error processing webhook', 500);
		}
	}

	/**
	 * Handle delivered event
	 */
	private function handleDelivered(MessageDelivery $delivery, array $data)
	{
		$delivery->update([
			'delivered_at' => now(),
			'delivery_status' => 'delivered',
			'status_id' => 3, // delivered
			'provider_data' => array_merge($delivery->provider_data ?? [], [$data]),
		]);

		Log::info('MailBaby webhook: Email delivered', [
			'delivery_id' => $delivery->id,
			'provider_message_id' => $delivery->provider_message_id,
			'contact_email' => $delivery->contact->email ?? 'unknown',
		]);
	}

	/**
	 * Handle bounced event
	 */
	private function handleBounced(MessageDelivery $delivery, array $data)
	{
		$delivery->update([
			'bounced_at' => now(),
			'delivery_status' => 'bounced',
			'status_id' => 4, // bounced/failed
			'provider_data' => array_merge($delivery->provider_data ?? [], [$data]),
		]);

		Log::warning('MailBaby webhook: Email bounced', [
			'delivery_id' => $delivery->id,
			'provider_message_id' => $delivery->provider_message_id,
			'contact_email' => $delivery->contact->email ?? 'unknown',
			'bounce_reason' => $data['reason'] ?? 'unknown',
		]);
	}

	/**
	 * Handle opened event
	 */
	private function handleOpened(MessageDelivery $delivery, array $data)
	{
		$delivery->update([
			'opened_at' => now(),
			'provider_data' => array_merge($delivery->provider_data ?? [], [$data]),
		]);

		Log::info('MailBaby webhook: Email opened', [
			'delivery_id' => $delivery->id,
			'provider_message_id' => $delivery->provider_message_id,
			'contact_email' => $delivery->contact->email ?? 'unknown',
		]);
	}

	/**
	 * Handle clicked event
	 */
	private function handleClicked(MessageDelivery $delivery, array $data)
	{
		$delivery->update([
			'clicked_at' => now(),
			'provider_data' => array_merge($delivery->provider_data ?? [], [$data]),
		]);

		Log::info('MailBaby webhook: Email clicked', [
			'delivery_id' => $delivery->id,
			'provider_message_id' => $delivery->provider_message_id,
			'contact_email' => $delivery->contact->email ?? 'unknown',
			'url' => $data['url'] ?? 'unknown',
		]);
	}

	/**
	 * Handle failed event
	 */
	private function handleFailed(MessageDelivery $delivery, array $data)
	{
		$delivery->update([
			'delivery_status' => 'failed',
			'status_id' => 4, // failed
			'provider_data' => array_merge($delivery->provider_data ?? [], [$data]),
		]);

		Log::error('MailBaby webhook: Email failed', [
			'delivery_id' => $delivery->id,
			'provider_message_id' => $delivery->provider_message_id,
			'contact_email' => $delivery->contact->email ?? 'unknown',
			'error' => $data['error'] ?? 'unknown',
		]);
	}
}
