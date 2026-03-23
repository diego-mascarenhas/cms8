<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Team;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function handleIncomingMessage(Request $request, $hash = null)
    {
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

        $messageService = new WhatsAppMessageService($team);

        if (! $messageService->isConfigured())
        {
            $teamInfo = $team ? "team: {$team->name}" : 'global .env configuration';
            Log::error("Twilio not configured for {$teamInfo}");

            return response('Twilio not configured', 500);
        }

        return $messageService->processIncomingMessage($request);
    }

    public function handleMessageStatus(Request $request, $hash = null)
    {
        $team = null;
        if ($hash)
        {
            $team = Team::findByWebhookHash($hash);
        }

        Log::info('Message status callback received', array_merge($request->all(), [
            'team_id' => $team ? $team->id : 'global',
            'team_name' => $team ? $team->name : 'Global .env config',
        ]));

        $messageSid = $request->input('MessageSid');
        $messageStatus = $request->input('MessageStatus');

        $message = Conversation::where('message_sid', $messageSid)->first();

        if ($message)
        {
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

    public function handleFallback(Request $request, $hash = null)
    {
        $team = null;
        if ($hash)
        {
            $team = Team::findByWebhookHash($hash);
        }

        Log::warning('Fallback webhook triggered', $request->all());

        try
        {
            $messageSid = $request->input('MessageSid');
            $from = $request->input('From');
            $to = $request->input('To');
            $body = $request->input('Body');
            $numMedia = (int) $request->input('NumMedia', 0);

            $channel = 'sms';
            if (strpos($from, 'whatsapp:') !== false || strpos($to, 'whatsapp:') !== false)
            {
                $channel = 'whatsapp';
            }

            Log::info("Fallback: Incoming {$channel} message from {$from}: {$body}");

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
