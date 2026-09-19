<?php

namespace App\Services\Communications;

use App\Enums\CommunicationChannel;
use App\Mail\CommunicationMail;
use App\Models\Communication;
use App\Models\Team;
use App\Services\MailBabyService;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class CommunicationSender
{
    use ConfiguresTeamMail;

    public function send(Communication $communication): void
    {
        $team = $communication->team;
        if (! $team)
        {
            throw new RuntimeException('Communication is missing a team.');
        }

        match ($communication->channel)
        {
            CommunicationChannel::Email => $this->sendEmail($communication, $team),
            CommunicationChannel::WhatsApp => $this->sendWhatsApp($communication, $team),
            CommunicationChannel::Sms => $this->sendSms($communication, $team),
        };
    }

    public function sendEmail(Communication $communication, Team $team): void
    {
        if (! $communication->recipient_email)
        {
            throw new RuntimeException('Recipient email is required for email communications.');
        }

        $sender = $this->resolveEmailSender($team);

        if (config('services.mailbaby.enabled') && config('services.mailbaby.api_key'))
        {
            try
            {
                $this->sendEmailViaMailBaby($communication, $sender);

                return;
            } catch (\Throwable $exception)
            {
                Log::warning('Communications MailBaby API failed, falling back to SMTP', [
                    'communication_id' => $communication->id,
                    'error' => $exception->getMessage(),
                ]);

                if (! config('services.email.fallback_to_smtp', true))
                {
                    throw $exception;
                }
            }
        }

        $this->configureMailForTeam($team, false);

        Mail::to($communication->recipient_email, $communication->recipient_name)
            ->send(new CommunicationMail($communication));
    }

    /**
     * @return array{from_name: string, from_address: string}
     */
    private function resolveEmailSender(Team $team): array
    {
        $sender = $team->getTeamEmailSender();
        if ($sender['from_address'] === '')
        {
            $sender = $team->getMailerEmailSender();
        }

        if ($sender['from_address'] === '')
        {
            throw new RuntimeException(__('Configurá el remitente de email en Configuración antes de enviar.'));
        }

        return $sender;
    }

    /**
     * @param  array{from_name: string, from_address: string}  $sender
     */
    private function sendEmailViaMailBaby(Communication $communication, array $sender): void
    {
        $from = $sender['from_name'] !== ''
            ? $sender['from_name'].' <'.$sender['from_address'].'>'
            : $sender['from_address'];

        $emailData = [
            'to' => $communication->recipient_email,
            'from' => $from,
            'subject' => $communication->subject ?: __('Communications'),
            'body' => view('emails.communication', ['communication' => $communication])->render(),
            'message_id' => 'communication-'.$communication->id,
            'attachments' => $this->mailBabyAttachments($communication),
        ];

        $result = app(MailBabyService::class)->sendEmail($emailData);
        if (! ($result['success'] ?? false))
        {
            throw new RuntimeException('MailBaby API request failed: '.($result['error'] ?? 'Unknown error'));
        }

        $communication->storeProviderTracking(
            'mailbaby',
            is_string($result['message_id'] ?? null) ? $result['message_id'] : null,
        );
    }

    /**
     * @return list<array{filename: string, data: string}>
     */
    private function mailBabyAttachments(Communication $communication): array
    {
        $attachments = [];

        foreach ($communication->getMedia('attachments') as $media)
        {
            $path = $media->getPath();
            if (! is_string($path) || $path === '' || ! file_exists($path))
            {
                continue;
            }

            $attachments[] = [
                'filename' => $media->file_name,
                'data' => base64_encode((string) file_get_contents($path)),
            ];
        }

        return $attachments;
    }

    public function sendWhatsApp(Communication $communication, Team $team): void
    {
        $to = (string) $communication->recipient_phone;
        if ($to === '')
        {
            throw new RuntimeException('Recipient phone is required for WhatsApp communications.');
        }

        $service = new WhatsAppMessageService($team);
        $service->sendWhatsApp($to, $communication->message, [
            'source' => 'communications',
            'communication_id' => $communication->id,
        ]);
    }

    public function sendSms(Communication $communication, Team $team): void
    {
        $to = (string) $communication->recipient_phone;
        if ($to === '')
        {
            throw new RuntimeException('Recipient phone is required for SMS communications.');
        }

        $service = new WhatsAppMessageService($team);
        $service->sendSms($to, $communication->message);
    }
}
