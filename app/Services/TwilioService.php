<?php

namespace App\Services;

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
            $message = $this->client->messages->create(
                $to,
                [
                    'from' => $this->smsFromNumber,
                    'body' => $message
                ]
            );
            
            return $message;
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
            
            $message = $this->client->messages->create(
                $formattedTo,
                [
                    'from' => $this->whatsappFromNumber,
                    'body' => $message
                ]
            );
            
            return $message;
        } catch (\Exception $e) {
            Log::error('Twilio WhatsApp Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function processIncomingMessage($request)
    {
        $from = $request->input('From');
        $body = $request->input('Body');
        
        // Log the incoming message
        Log::info("Incoming message from {$from}: {$body}");
        
        // Here you can add your business logic to process the message
        // For example, you could:
        // - Store the message in your database
        // - Trigger automated responses
        // - Forward to a human agent
        
        return response()->json(['status' => 'success']);
    }
} 