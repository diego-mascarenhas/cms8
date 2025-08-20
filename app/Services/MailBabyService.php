<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MailBabyService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.mailbaby.api_key');
        $this->baseUrl = config('services.mailbaby.api_url', 'https://api.mailbaby.net');
    }

    /**
     * Send email via MailBaby API
     */
    public function sendEmail(array $emailData)
    {
        try {
            $payload = [
                'to' => $emailData['to'],
                'from' => $emailData['from'],
                'subject' => $emailData['subject'],
                'body' => $emailData['body'],
            ];

            // Add optional fields only if they have values
            if (!empty($emailData['reply_to'])) {
                $payload['replyto'] = $emailData['reply_to'];
            }
            if (!empty($emailData['cc'])) {
                $payload['cc'] = $emailData['cc'];
            }
            if (!empty($emailData['bcc'])) {
                $payload['bcc'] = $emailData['bcc'];
            }
            if (!empty($emailData['attachments'])) {
                $payload['attachments'] = $emailData['attachments'];
            }
            // Remove the custom ID for now - might not be supported
            // if (!empty($emailData['message_id'])) {
            //     $payload['id'] = $emailData['message_id'];
            // }

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->baseUrl . '/mail/send', $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('MailBaby: Email sent successfully', [
                    'message_id' => $emailData['message_id'] ?? null,
                    'to' => $emailData['to'],
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'message_id' => $data['id'] ?? null,
                    'data' => $data,
                ];
            } else {
                Log::error('MailBaby: Failed to send email', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'message_id' => $emailData['message_id'] ?? null,
                ]);

                return [
                    'success' => false,
                    'error' => $response->body(),
                    'status' => $response->status(),
                ];
            }

        } catch (\Exception $e) {
            Log::error('MailBaby: Exception sending email', [
                'error' => $e->getMessage(),
                'message_id' => $emailData['message_id'] ?? null,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get email status by ID
     */
    public function getEmailStatus($mailbabyId)
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/mail/status/' . $mailbabyId);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('MailBaby: Failed to get email status', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'mailbaby_id' => $mailbabyId,
                ]);
                return null;
            }

        } catch (\Exception $e) {
            Log::error('MailBaby: Exception getting email status', [
                'error' => $e->getMessage(),
                'mailbaby_id' => $mailbabyId,
            ]);
            return null;
        }
    }

    /**
     * Get account information
     */
    public function getAccountInfo()
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/mail/account');

            if ($response->successful()) {
                return $response->json();
            }

            return null;

        } catch (\Exception $e) {
            Log::error('MailBaby: Exception getting account info', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Validate webhook signature (if MailBaby provides HMAC signatures)
     */
    public function validateWebhookSignature($payload, $signature, $secret = null)
    {
        if (!$secret) {
            $secret = config('services.mailbaby.webhook_secret');
        }

        if (!$secret || !$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
