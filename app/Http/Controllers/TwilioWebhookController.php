<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Team;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TwilioWebhookController extends Controller
{
    public function handleIncomingMessage(Request $request, $hash = null)
    {
        // Find team by webhook hash if provided
        $team = null;
        if ($hash)
        {
            $team = Team::findByWebhookHash($hash);
            if (! $team)
            {
                Log::warning("Invalid webhook hash: {$hash}");

                return response('Invalid webhook hash', 404);
            }
        }
        // If no hash provided, leave $team as null to use global .env configuration

        // Create TwilioService instance with the correct team (or null for global config)
        $twilioService = new TwilioService($team);

        if (! $twilioService->isConfigured())
        {
            $teamInfo = $team ? "team: {$team->name}" : 'global .env configuration';
            Log::error("Twilio not configured for {$teamInfo}");

            return response('Twilio not configured', 500);
        }

        return $twilioService->processIncomingMessage($request);
    }

    /**
     * Handle status callbacks for outbound messages
     *
     * @return \Illuminate\Http\Response
     */
    public function handleMessageStatus(Request $request, $hash = null)
    {
        // Find team by hash if provided (for logging purposes)
        $team = null;
        if ($hash)
        {
            $team = Team::findByWebhookHash($hash);
        }
        // If no hash provided, leave $team as null (using global .env configuration)

        // Log the status callback for debugging
        Log::info('Message status callback received', array_merge($request->all(), [
            'team_id' => $team ? $team->id : 'global',
            'team_name' => $team ? $team->name : 'Global .env config',
        ]));

        // Get important data from the callback
        $messageSid = $request->input('MessageSid');
        $messageStatus = $request->input('MessageStatus');

        // Find the corresponding message in our database
        $message = Conversation::where('message_sid', $messageSid)->first();

        if ($message)
        {
            // Update the message status
            $message->status = $messageStatus;
            $message->save();

            Log::info("Message status updated: {$messageSid} to {$messageStatus}", [
                'team_id' => $team ? $team->id : 'global',
            ]);
        } else
        {
            Log::warning("Message not found for SID: {$messageSid}", [
                'team_id' => $team ? $team->id : 'global',
            ]);
        }

        return response('Status callback processed', 200);
    }

    /**
     * Handle fallback for when the primary webhook fails
     *
     * @return \Illuminate\Http\Response
     */
    public function handleFallback(Request $request, $hash = null)
    {
        // Find team by hash if provided
        $team = null;
        if ($hash)
        {
            $team = Team::findByWebhookHash($hash);
        }
        // If no hash provided, leave $team as null (using global .env configuration)

        $teamInfo = $team ? "team: {$team->name}" : 'global .env configuration';

        // Log that the fallback was triggered
        Log::warning('Fallback webhook triggered', $request->all());

        try
        {
            // Process the message as a normal incoming message
            // but mark it as coming through the fallback
            $messageSid = $request->input('MessageSid');
            $from = $request->input('From');
            $to = $request->input('To');
            $body = $request->input('Body');
            $numMedia = (int) $request->input('NumMedia', 0);

            // Determine the channel type
            $channel = 'sms';
            if (strpos($from, 'whatsapp:') !== false || strpos($to, 'whatsapp:') !== false)
            {
                $channel = 'whatsapp';
            }

            // Log the incoming message
            Log::info("Fallback: Incoming {$channel} message from {$from}: {$body}");

            // Process media if present
            $media = [];
            if ($numMedia > 0)
            {
                for ($i = 0; $i < $numMedia; $i++)
                {
                    $mediaUrl = $request->input("MediaUrl{$i}");
                    $contentType = $request->input("MediaContentType{$i}");
                    $media[] = [
                        'url' => $mediaUrl,
                        'content_type' => $contentType,
                    ];
                }
            }

            // Save incoming message to database
            $conversation = Conversation::create([
                'message_sid' => $messageSid,
                'channel' => $channel,
                'from' => $from,
                'to' => $to,
                'body' => $body,
                'status' => 'received',
                'direction' => 'inbound',
                'media' => ! empty($media) ? $media : null,
                'metadata' => array_merge($request->except(['_token']), ['via_fallback' => true]),
            ]);

            // Send an automatic response letting the user know the system received their message
            // but might be experiencing technical difficulties
            $responseMessage = 'Hemos recibido tu mensaje. Nuestro equipo lo revisará lo antes posible.';

            return response()->json([
                'status' => 'success',
                'conversation_id' => $conversation->id,
                'via_fallback' => true,
            ]);
        } catch (\Exception $e)
        {
            Log::error('Error processing fallback message: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
