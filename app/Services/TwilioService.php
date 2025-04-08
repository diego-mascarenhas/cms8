<?php

namespace App\Services;

use App\Models\Conversation;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

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
        $this->whatsappFromNumber = 'whatsapp:+14155238886'; // Sandbox number
        
        $this->client = new Client($sid, $token);
    }

    public function sendSms($to, $message)
    {
        try {
            $twilioMessage = $this->client->messages->create(
                $to,
                [
                    'from' => $this->smsFromNumber,
                    'body' => $message
                ]
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
                'metadata' => [
                    'twilio_response' => [
                        'sid' => $twilioMessage->sid,
                        'status' => $twilioMessage->status,
                        'date_created' => $twilioMessage->dateCreated->format('Y-m-d H:i:s'),
                    ]
                ]
            ]);
            
            return $twilioMessage;
        } catch (\Exception $e) {
            Log::error('Twilio SMS Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function sendWhatsApp($to, $message)
    {
        try {
            // Format the numbers with whatsapp: prefix
            $formattedTo = 'whatsapp:' . $to;
            
            $twilioMessage = $this->client->messages->create(
                $formattedTo,
                [
                    'from' => $this->whatsappFromNumber,
                    'body' => $message
                ]
            );

            // Save outbound message to database
            Conversation::create([
                'message_sid' => $twilioMessage->sid,
                'channel' => 'whatsapp',
                'from' => $this->whatsappFromNumber,
                'to' => $formattedTo,
                'body' => $message,
                'status' => 'sent',
                'direction' => 'outbound',
                'metadata' => [
                    'twilio_response' => [
                        'sid' => $twilioMessage->sid,
                        'status' => $twilioMessage->status,
                        'date_created' => $twilioMessage->dateCreated->format('Y-m-d H:i:s'),
                    ]
                ]
            ]);
            
            return $twilioMessage;
        } catch (\Exception $e) {
            Log::error('Twilio WhatsApp Error: ' . $e->getMessage());
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
            $numMedia = (int)$request->input('NumMedia', 0);
            
            // Determine the channel type
            $channel = 'sms';
            if (strpos($from, 'whatsapp:') !== false || strpos($to, 'whatsapp:') !== false) {
                $channel = 'whatsapp';
            }
            
            // Log the incoming message
            Log::info("Incoming {$channel} message from {$from}: {$body}");
            
            // Process media if present
            $media = [];
            if ($numMedia > 0) {
                for ($i = 0; $i < $numMedia; $i++) {
                    $mediaUrl = $request->input("MediaUrl{$i}");
                    $contentType = $request->input("MediaContentType{$i}");
                    $media[] = [
                        'url' => $mediaUrl,
                        'content_type' => $contentType
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
                'media' => !empty($media) ? $media : null,
                'metadata' => $request->except(['_token'])
            ]);
            
            // Here you can add your business logic for automated responses
            // For example, you could call an AI service to generate a response
            
            return response()->json(['status' => 'success', 'conversation_id' => $conversation->id]);
        } catch (\Exception $e) {
            Log::error('Error processing incoming message: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
} 