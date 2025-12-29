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
        // Determine bounce type (hard, soft, complaint, block)
        $bounceType = $this->determineBounceType($data);
        $bounceReason = $data['reason'] ?? $data['message'] ?? $data['error'] ?? 'Unknown bounce reason';

        $delivery->update([
            'bounced_at' => now(),
            'delivery_status' => 'bounced',
            'status_id' => 4, // bounced/failed
            'error_type' => 'bounce',
            'bounce_type' => $bounceType,
            'bounce_reason' => $bounceReason,
            'error_message' => $bounceReason,
            'provider_data' => array_merge($delivery->provider_data ?? [], [$data]),
        ]);

        Log::warning('MailBaby webhook: Email bounced', [
            'delivery_id' => $delivery->id,
            'provider_message_id' => $delivery->provider_message_id,
            'contact_email' => $delivery->contact->email ?? 'unknown',
            'bounce_type' => $bounceType,
            'bounce_reason' => $bounceReason,
        ]);
    }

    /**
     * Determine bounce type from webhook data
     */
    private function determineBounceType(array $data): string
    {
        $reason = strtolower($data['reason'] ?? $data['message'] ?? $data['type'] ?? '');

        // Hard bounces (permanent failures)
        if (str_contains($reason, 'permanent') ||
            str_contains($reason, 'invalid') ||
            str_contains($reason, 'not exist') ||
            str_contains($reason, 'unknown user') ||
            str_contains($reason, 'mailbox not found'))
        {
            return 'hard';
        }

        // Soft bounces (temporary failures)
        if (str_contains($reason, 'temporary') ||
            str_contains($reason, 'mailbox full') ||
            str_contains($reason, 'quota exceeded') ||
            str_contains($reason, 'try again'))
        {
            return 'soft';
        }

        // Complaints (spam reports)
        if (str_contains($reason, 'complaint') ||
            str_contains($reason, 'spam') ||
            str_contains($reason, 'abuse'))
        {
            return 'complaint';
        }

        // Blocks (blacklisted, blocked)
        if (str_contains($reason, 'block') ||
            str_contains($reason, 'blacklist') ||
            str_contains($reason, 'reputation'))
        {
            return 'block';
        }

        return 'unknown';
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
        $errorReason = $data['error'] ?? $data['reason'] ?? $data['message'] ?? 'Unknown error';

        $delivery->update([
            'delivery_status' => 'failed',
            'status_id' => 4, // failed
            'error_type' => 'smtp_error', // SMTP/API error (not a bounce)
            'error_message' => $errorReason,
            'provider_data' => array_merge($delivery->provider_data ?? [], [$data]),
        ]);

        Log::error('MailBaby webhook: Email failed', [
            'delivery_id' => $delivery->id,
            'provider_message_id' => $delivery->provider_message_id,
            'contact_email' => $delivery->contact->email ?? 'unknown',
            'error' => $errorReason,
        ]);
    }
}
