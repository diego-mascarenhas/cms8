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
        try
        {
            $payload = [
                'to' => $emailData['to'],
                'from' => $emailData['from'],
                'subject' => $emailData['subject'],
                'body' => $emailData['body'],
            ];

            $orderId = $this->mailOrderId();
            if ($orderId !== null)
            {
                $payload['id'] = $orderId;
            }

            if (! empty($emailData['reply_to']))
            {
                $payload['replyto'] = $emailData['reply_to'];
            }
            if (! empty($emailData['cc']))
            {
                $payload['cc'] = $emailData['cc'];
            }
            if (! empty($emailData['bcc']))
            {
                $payload['bcc'] = $emailData['bcc'];
            }
            if (! empty($emailData['attachments']))
            {
                $payload['attachments'] = $emailData['attachments'];
            }

            $endpoint = $this->usesAdvancedSend($payload) ? '/mail/advsend' : '/mail/send';

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->baseUrl.$endpoint, $payload);

            if ($response->successful())
            {
                $data = $response->json();

                Log::info('MailBaby: Email sent successfully', [
                    'message_id' => $emailData['message_id'] ?? null,
                    'to' => $emailData['to'],
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'message_id' => $this->extractMailId(is_array($data) ? $data : []),
                    'data' => $data,
                ];
            } else
            {
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
        } catch (\Exception $e)
        {
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
     * @param  array<string, mixed>  $data
     */
    public function extractMailId(array $data): ?string
    {
        foreach (['id', 'text', 'mailid'] as $key)
        {
            $value = $data[$key] ?? null;
            if (is_string($value) && preg_match('/^[a-f0-9]{18,19}$/i', $value))
            {
                return strtolower($value);
            }
        }

        $text = $data['text'] ?? null;

        return is_string($text) && $text !== '' ? $text : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDeliveryRecord(?string $mailId, ?string $recipient, ?string $subject, ?string $startDate): ?array
    {
        if (is_string($mailId) && preg_match('/^[a-f0-9]{18,19}$/i', $mailId))
        {
            $byId = $this->firstLogEmail($this->getMailLog($mailId, ['limit' => 5]));
            if (is_array($byId))
            {
                return $this->presentLogEntry($byId);
            }
        }

        if (! is_string($recipient) || ! str_contains($recipient, '@'))
        {
            return null;
        }

        $log = $this->getMailLog(null, [
            'to' => $recipient,
            'startDate' => $startDate,
            'limit' => 20,
        ]);
        $emails = $this->logEmails($log);
        if ($emails === [])
        {
            return null;
        }

        $match = $emails[0];
        $needle = is_string($subject) ? mb_strtolower(trim($subject)) : '';
        if ($needle !== '')
        {
            foreach ($emails as $email)
            {
                $rowSubject = mb_strtolower(trim((string) ($email['subject'] ?? '')));
                if ($rowSubject !== '' && (str_contains($rowSubject, $needle) || str_contains($needle, $rowSubject)))
                {
                    $match = $email;
                    break;
                }
            }
        }

        return $this->presentLogEntry($match);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMailLog(?string $mailId, array $filters = []): ?array
    {
        try
        {
            $query = array_filter([
                'id' => $filters['id'] ?? $this->mailOrderId(),
                'mailid' => $mailId,
                'to' => $filters['to'] ?? null,
                'subject' => $filters['subject'] ?? null,
                'startDate' => $filters['startDate'] ?? null,
                'limit' => $filters['limit'] ?? 1,
            ], fn ($value) => $value !== null && $value !== '');

            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl.'/mail/log', $query);

            if ($response->successful())
            {
                return $response->json();
            }

            Log::error('MailBaby: Failed to get mail log', [
                'status' => $response->status(),
                'response' => $response->body(),
                'mailid' => $mailId,
            ]);

            return null;
        } catch (\Exception $e)
        {
            Log::error('MailBaby: Exception getting mail log', [
                'error' => $e->getMessage(),
                'mailid' => $mailId,
            ]);

            return null;
        }
    }

    /**
     * Get email status by ID
     */
    public function getEmailStatus($mailbabyId)
    {
        $log = $this->getMailLog(is_string($mailbabyId) ? $mailbabyId : null);
        if (is_array($log))
        {
            $emails = $log['emails'] ?? [];
            if (is_array($emails) && $emails !== [])
            {
                return $emails[0];
            }
        }

        try
        {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl.'/mail/status/'.$mailbabyId);

            if ($response->successful())
            {
                return $response->json();
            } else
            {
                Log::error('MailBaby: Failed to get email status', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'mailbaby_id' => $mailbabyId,
                ]);

                return null;
            }
        } catch (\Exception $e)
        {
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
        try
        {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl.'/mail/account');

            if ($response->successful())
            {
                return $response->json();
            }

            return null;
        } catch (\Exception $e)
        {
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
        if (! $secret)
        {
            $secret = config('services.mailbaby.webhook_secret');
        }

        if (! $secret || ! $signature)
        {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function usesAdvancedSend(array $payload): bool
    {
        if (! empty($payload['replyto']) || ! empty($payload['cc']) || ! empty($payload['bcc']) || ! empty($payload['attachments']))
        {
            return true;
        }

        return is_string($payload['from'] ?? null) && str_contains($payload['from'], '<');
    }

    private function mailOrderId(): ?int
    {
        $id = config('services.mailbaby.order_id');
        if (! is_numeric($id) || (int) $id <= 0)
        {
            return null;
        }

        return (int) $id;
    }

    /**
     * @param  array<string, mixed>|null  $log
     * @return list<array<string, mixed>>
     */
    private function logEmails(?array $log): array
    {
        $emails = is_array($log) ? ($log['emails'] ?? null) : null;
        if (! is_array($emails))
        {
            return [];
        }

        return array_values(array_filter($emails, 'is_array'));
    }

    /**
     * @param  array<string, mixed>|null  $log
     * @return array<string, mixed>|null
     */
    private function firstLogEmail(?array $log): ?array
    {
        $emails = $this->logEmails($log);

        return $emails[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function presentLogEntry(array $row): array
    {
        return [
            'found' => true,
            'id' => $row['id'] ?? null,
            'delivered' => (int) ($row['delivered'] ?? 0) === 1,
            'code' => $row['code'] ?? null,
            'response' => $row['response'] ?? null,
            'created' => $row['created'] ?? null,
            'user' => $row['user'] ?? null,
            'subject' => $row['subject'] ?? null,
            'from' => $row['from'] ?? null,
            'to' => $row['to'] ?? null,
        ];
    }
}
