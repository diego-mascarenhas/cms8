<?php

namespace App\Services;

use App\Mail\IncomingMessageNotification;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client;

class TwilioService
{
    protected $client;

    protected $smsFromNumber;

    protected $whatsappFromNumber;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $this->smsFromNumber = config('services.twilio.from');
        $this->whatsappFromNumber = 'whatsapp:'.config('services.twilio.whatsapp_from', '+14155238886');

        $this->client = new Client($sid, $token);
    }

    public function sendSms($to, $message)
    {
        try {
            // Get the full URL for the status callback
            $statusCallbackUrl = url(route('twilio.status'));

            $twilioMessage = $this->client->messages->create(
                $to,
                [
                    'from' => $this->smsFromNumber,
                    'body' => $message,
                    'statusCallback' => $statusCallbackUrl,
                ],
            );

            // Save outbound SMS to database
            Conversation::create([
                'message_sid' => $twilioMessage->sid,
                'channel' => 'sms',
                'from' => $this->smsFromNumber,
                'to' => $to,
                'body' => $message,
                'status' => 'sent',
                'direction' => 'outbound',
                'user_id' => auth()->id(),
                'metadata' => [
                    'twilio_response' => [
                        'sid' => $twilioMessage->sid,
                        'status' => $twilioMessage->status,
                        'date_created' => $twilioMessage->dateCreated->format('Y-m-d H:i:s'),
                    ],
                ],
            ]);

            return $twilioMessage;
        } catch (\Exception $e) {
            Log::error('Twilio SMS Error: '.$e->getMessage());
            throw $e;
        }
    }

    public function sendWhatsApp($to, $message, $metadata = null, $userId = null)
    {
        try {
            // Format the numbers with whatsapp: prefix for Twilio
            $formattedTo = 'whatsapp:'.$to;

            // Get the full URL for the status callback
            $statusCallbackUrl = url(route('twilio.status'));

            $twilioMessage = $this->client->messages->create(
                $formattedTo,
                [
                    'from' => $this->whatsappFromNumber,
                    'body' => $message,
                    'statusCallback' => $statusCallbackUrl,
                ],
            );

            // Clean phone numbers before saving to database
            $cleanFrom = preg_replace('/[^0-9]/', '', $this->whatsappFromNumber);
            $cleanTo = preg_replace('/[^0-9]/', '', $to);

            // Prepare metadata
            $messageMetadata = [
                'twilio_response' => [
                    'sid' => $twilioMessage->sid,
                    'status' => $twilioMessage->status,
                    'date_created' => $twilioMessage->dateCreated->format('Y-m-d H:i:s'),
                ],
            ];

            // Merge custom metadata if provided
            if ($metadata) {
                $messageMetadata = array_merge($messageMetadata, $metadata);
            }

            // Save outbound message to database
            Conversation::create([
                'message_sid' => $twilioMessage->sid,
                'channel' => 'whatsapp',
                'from' => $cleanFrom,
                'to' => $cleanTo,
                'body' => $message,
                'status' => 'sent',
                'direction' => 'outbound',
                'user_id' => $userId ?? auth()->id(),
                'metadata' => $messageMetadata,
            ]);

            return $twilioMessage;
        } catch (\Exception $e) {
            Log::error('Twilio WhatsApp Error: '.$e->getMessage());
            throw $e;
        }
    }

    public function processIncomingMessage($request)
    {
        try {
            $messageSid = $request->input('MessageSid');
            $from = $request->input('From');
            $to = $request->input('To');
            $body = $request->input('Body');
            $numMedia = (int) $request->input('NumMedia', 0);

            // Clean phone numbers by removing whatsapp: prefix and non-numeric characters
            $cleanFrom = preg_replace('/[^0-9]/', '', $from);
            $cleanTo = preg_replace('/[^0-9]/', '', $to);

            // Determine the channel type
            $channel = 'sms';
            if (strpos($from, 'whatsapp:') !== false || strpos($to, 'whatsapp:') !== false) {
                $channel = 'whatsapp';
            }

            // Log the incoming message
            \Log::info("Incoming {$channel} message from {$cleanFrom}: {$body}");

            // Process media if present
            $media = [];
            if ($numMedia > 0) {
                for ($i = 0; $i < $numMedia; $i++) {
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
                'from' => $cleanFrom,
                'to' => $cleanTo,
                'body' => $body,
                'status' => 'received',
                'direction' => 'inbound',
                'media' => ! empty($media) ? $media : null,
                'metadata' => $request->except(['_token']),
            ]);

            // Send email notification for new message
            $notificationEmail = config('services.notifications.email');
            if ($notificationEmail) {
                Mail::to($notificationEmail)->send(new IncomingMessageNotification($conversation));
                \Log::info("Email notification sent to {$notificationEmail} for message {$messageSid}");
            }

            // Check if this is part of a registration process
            if ($channel == 'whatsapp') {
                $chatController = app(\App\Http\Controllers\ChatController::class);
                $registrationResponse = $chatController->processRegistration($cleanFrom, $body);

                // If this was a registration step, we've already handled it
                if ($registrationResponse) {
                    return response()->json(['status' => 'success', 'conversation_id' => $conversation->id, 'registration' => true]);
                }
            }

            // Automatic AI response using Claude
            if (config('services.claude.auto_respond', false) && $channel == 'whatsapp') {
                try {
                    // Get recent chat history for context
                    $history = Conversation::where('channel', 'whatsapp')
                        ->where(function ($query) use ($cleanFrom) {
                            $query->where('from', $cleanFrom)
                                ->orWhere('to', $cleanFrom);
                        })
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get()
                        ->sortBy('created_at')
                        ->values()
                        ->toArray();

                    // Process with Claude
                    $claudeService = app(\App\Services\ClaudeService::class);
                    $claudeResponse = $claudeService->chat($body, $history);

                    // If Claude responded successfully, send the response
                    if ($claudeResponse['success']) {
                        $aiMessage = $claudeResponse['text'];

                        // Send the AI message
                        $this->sendWhatsApp($cleanFrom, $aiMessage);

                        \Log::info("Auto AI response sent to {$cleanFrom}: ".\Illuminate\Support\Str::limit($aiMessage, 100));
                    } else {
                        \Log::warning('Failed to get AI response: '.($claudeResponse['message'] ?? 'Unknown error'));
                    }
                } catch (\Exception $e) {
                    \Log::error('Error in auto AI response: '.$e->getMessage());
                }
            }

            return response()->json(['status' => 'success', 'conversation_id' => $conversation->id]);
        } catch (\Exception $e) {
            \Log::error('Error processing incoming message: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Send WhatsApp message using a template
     *
     * @param  string  $to  Recipient phone number (without whatsapp: prefix)
     * @param  string  $templateName  The name of the approved template
     * @param  array  $parameters  Template parameters
     * @return \Twilio\Rest\Api\V2010\Account\MessageInstance
     */
    public function sendWhatsAppTemplate($to, $templateName, $parameters = [])
    {
        try {
            // Format the numbers with whatsapp: prefix
            $formattedTo = 'whatsapp:'.$to;

            // Get the full URL for the status callback
            $statusCallbackUrl = url(route('twilio.status'));

            // Prepare template parameters
            $contentSid = null;
            $contentVariables = null;

            if (! empty($parameters)) {
                $contentVariables = json_encode(['1' => $parameters]);
            }

            // Create message with template
            $twilioMessage = $this->client->messages->create(
                $formattedTo,
                [
                    'from' => $this->whatsappFromNumber,
                    'statusCallback' => $statusCallbackUrl,
                    'contentSid' => $templateName,
                    'contentVariables' => $contentVariables,
                ],
            );

            // Save outbound message to database
            Conversation::create([
                'message_sid' => $twilioMessage->sid,
                'channel' => 'whatsapp',
                'from' => $this->whatsappFromNumber,
                'to' => $formattedTo,
                'body' => "Template: {$templateName}",
                'status' => 'sent',
                'direction' => 'outbound',
                'user_id' => auth()->id(),
                'metadata' => [
                    'twilio_response' => [
                        'sid' => $twilioMessage->sid,
                        'status' => $twilioMessage->status,
                        'date_created' => $twilioMessage->dateCreated->format('Y-m-d H:i:s'),
                    ],
                    'template' => [
                        'name' => $templateName,
                        'parameters' => $parameters,
                    ],
                ],
            ]);

            return $twilioMessage;
        } catch (\Exception $e) {
            Log::error('Twilio WhatsApp Template Error: '.$e->getMessage());
            throw $e;
        }
    }
}
