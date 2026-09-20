<?php

namespace App\Services\Communications;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Jobs\SendCommunicationJob;
use App\Models\Communication;
use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

class CommunicationService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $attachments
     */
    public function create(Team $team, array $data, ?User $user = null, array $attachments = []): Communication
    {
        $channel = $data['channel'] instanceof CommunicationChannel
            ? $data['channel']
            : CommunicationChannel::from((string) $data['channel']);

        $email = $this->normalizeEmail($data['recipient_email'] ?? null);
        $phone = $this->normalizePhone($data['recipient_phone'] ?? null);
        $contact = $this->resolveContact($team, $data['contact_id'] ?? null, $email, $phone);

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $attachmentCount = count($attachments);
        $metadata['events'] = [[
            'type' => 'queued',
            'at' => now()->toIso8601String(),
            'message' => $attachmentCount > 0
                ? $attachmentCount === 1 ? '1 adjunto' : $attachmentCount.' adjuntos'
                : null,
        ]];

        $communication = Communication::query()->create([
            'team_id' => $team->id,
            'user_id' => $user?->id,
            'contact_id' => $contact?->id,
            'channel' => $channel,
            'recipient_email' => $email,
            'recipient_phone' => $phone,
            'recipient_name' => $this->nullableString($data['recipient_name'] ?? $contact?->name),
            'subject' => $this->nullableString($data['subject'] ?? null),
            'message' => (string) $data['message'],
            'status' => CommunicationStatus::Pending,
            'metadata' => $metadata,
        ]);

        foreach ($attachments as $attachment)
        {
            $communication->addMedia($attachment)->toMediaCollection('attachments');
        }

        SendCommunicationJob::dispatch($communication->id);

        return $communication->fresh(['contact']) ?? $communication;
    }

    public function retry(Communication $communication): Communication
    {
        $communication->forceFill([
            'status' => CommunicationStatus::Pending,
            'error_message' => null,
            'sent_at' => null,
            'metadata' => $communication->withEvent('retried'),
        ])->save();

        SendCommunicationJob::dispatch($communication->id);

        return $communication->fresh(['contact']) ?? $communication;
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(Team $team): array
    {
        $query = Communication::query()->where('team_id', $team->id);
        $total = (clone $query)->count();
        $pending = (clone $query)->where('status', CommunicationStatus::Pending)->count();
        $failed = (clone $query)->where('status', CommunicationStatus::Failed)->count();
        $sent = (clone $query)->where('status', CommunicationStatus::Sent)->count();
        $sentToday = (clone $query)
            ->where('status', CommunicationStatus::Sent)
            ->whereDate('sent_at', Carbon::today())
            ->count();

        $successRate = $total > 0
            ? round(($sent / $total) * 100, 1)
            : 0.0;

        return [
            'total' => $total,
            'pending' => $pending,
            'failed' => $failed,
            'sent' => $sent,
            'sent_today' => $sentToday,
            'success_rate' => $successRate,
            'daily' => $this->dailyStats($team),
        ];
    }

    /**
     * @return list<array{date: string, sent: int, failed: int, pending: int}>
     */
    private function dailyStats(Team $team): array
    {
        $from = Carbon::today()->subDays(13)->startOfDay();
        $days = [];

        for ($offset = 13; $offset >= 0; $offset--)
        {
            $date = Carbon::today()->subDays($offset)->toDateString();
            $days[$date] = [
                'date' => $date,
                'sent' => 0,
                'failed' => 0,
                'pending' => 0,
            ];
        }

        $rows = Communication::query()
            ->where('team_id', $team->id)
            ->where(function ($builder) use ($from)
            {
                $builder->where('created_at', '>=', $from)
                    ->orWhere('sent_at', '>=', $from);
            })
            ->get(['status', 'sent_at', 'created_at']);

        foreach ($rows as $row)
        {
            $createdDay = $row->created_at?->toDateString();
            $sentDay = $row->sent_at?->toDateString();

            if ($row->status === CommunicationStatus::Sent && $sentDay && isset($days[$sentDay]))
            {
                $days[$sentDay]['sent']++;

                continue;
            }

            if ($row->status === CommunicationStatus::Failed && $createdDay && isset($days[$createdDay]))
            {
                $days[$createdDay]['failed']++;

                continue;
            }

            if ($row->status === CommunicationStatus::Pending && $createdDay && isset($days[$createdDay]))
            {
                $days[$createdDay]['pending']++;
            }
        }

        return array_values($days);
    }

    /**
     * @return array<string, mixed>
     */
    public function channelStatus(Team $team): array
    {
        $emailSender = $team->getTeamEmailSender();
        if ($emailSender['from_address'] === '')
        {
            $emailSender = $team->getMailerEmailSender();
        }

        return [
            'email' => [
                'configured' => $emailSender['from_address'] !== '' || $team->hasOutgoingEmailConfig(),
                'from_name' => $emailSender['from_name'],
                'from_address' => $emailSender['from_address'],
            ],
            'whatsapp' => [
                'configured' => $team->usesLocalWhatsApp()
                    ? $team->getWhatsAppServiceBaseUrl() !== ''
                    : $team->hasTwilioConfig(),
            ],
            'sms' => [
                'configured' => $team->hasTwilioConfig()
                    && trim((string) $team->getSetting('twilio_sms_from')) !== '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function format(Communication $communication, bool $includeMessage = true): array
    {
        $communication->loadMissing(['contact']);

        $payload = [
            'id' => $communication->id,
            'channel' => $communication->channel->value,
            'channel_label' => $communication->channel->label(),
            'status' => $communication->status->value,
            'status_label' => $communication->status->label(),
            'recipient_email' => $communication->recipient_email,
            'recipient_phone' => $communication->recipient_phone,
            'recipient_name' => $communication->recipient_name,
            'subject' => $communication->subject,
            'error_message' => $communication->error_message,
            'metadata' => $communication->metadata,
            'sent_at' => $communication->sent_at?->toIso8601String(),
            'created_at' => $communication->created_at?->toIso8601String(),
            'contact' => $communication->contact ? [
                'id' => $communication->contact->id,
                'name' => $communication->contact->name,
                'email' => $communication->contact->email,
                'phone' => $communication->contact->phone,
            ] : null,
            'attachments' => $communication->getMedia('attachments')->map(fn ($media) => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'url' => $media->getUrl(),
            ])->values()->all(),
            'tracking' => [
                'opened' => $communication->hasOpened(),
                'clicks' => collect($communication->events())->where('type', 'clicked')->count(),
                'provider' => is_array($communication->metadata)
                    ? ($communication->metadata['email_provider'] ?? null)
                    : null,
                'mail_id' => is_array($communication->metadata)
                    ? ($communication->metadata['provider_message_id'] ?? null)
                    : null,
            ],
        ];

        if ($includeMessage)
        {
            $payload['message'] = $communication->message;
            $payload['events'] = $this->events($communication);
        }

        return $payload;
    }

    /**
     * @return array<int, array{type: string, at: ?string, message: ?string}>
     */
    private function events(Communication $communication): array
    {
        $stored = is_array($communication->metadata) ? ($communication->metadata['events'] ?? null) : null;
        if (is_array($stored) && $stored !== [])
        {
            return array_values(array_map(function (mixed $event) use ($communication): array
            {
                $event = is_array($event) ? $event : [];

                return [
                    'type' => (string) ($event['type'] ?? 'queued'),
                    'at' => isset($event['at']) ? (string) $event['at'] : $communication->created_at?->toIso8601String(),
                    'message' => isset($event['message']) && $event['message'] !== ''
                        ? (string) $event['message']
                        : null,
                ];
            }, $stored));
        }

        $events = [[
            'type' => 'queued',
            'at' => $communication->created_at?->toIso8601String(),
            'message' => null,
        ]];

        if ($communication->status === CommunicationStatus::Sent && $communication->sent_at)
        {
            $events[] = [
                'type' => 'sent',
                'at' => $communication->sent_at->toIso8601String(),
                'message' => null,
            ];
        }

        if ($communication->status === CommunicationStatus::Failed)
        {
            $events[] = [
                'type' => 'failed',
                'at' => $communication->updated_at?->toIso8601String(),
                'message' => $communication->error_message,
            ];
        }

        if ($communication->status === CommunicationStatus::Pending && $communication->error_message)
        {
            $events[] = [
                'type' => 'attempt_failed',
                'at' => $communication->updated_at?->toIso8601String(),
                'message' => $communication->error_message,
            ];
        }

        return $events;
    }

    public function resolveContact(Team $team, mixed $contactId, ?string $email, ?string $phone): ?Contact
    {
        $query = Contact::withoutGlobalScopes()->where('team_id', $team->id);

        if ($contactId)
        {
            $contact = (clone $query)->whereKey($contactId)->first();
            if ($contact)
            {
                return $contact;
            }
        }

        if ($email)
        {
            $contact = (clone $query)->where('email', $email)->first();
            if ($contact)
            {
                return $contact;
            }
        }

        if ($phone)
        {
            return (clone $query)
                ->where(function ($builder) use ($phone)
                {
                    $builder->where('phone', $phone)
                        ->orWhere('phone', ltrim($phone, '0'));
                })
                ->first();
        }

        return null;
    }

    public function normalizeEmail(mixed $email): ?string
    {
        if (! is_string($email))
        {
            return null;
        }

        $email = strtolower(trim($email));

        return $email !== '' ? $email : null;
    }

    public function normalizePhone(mixed $phone): ?string
    {
        if (! is_string($phone) && ! is_numeric($phone))
        {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $phone);

        return $digits !== '' ? $digits : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value))
        {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
