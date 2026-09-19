<?php

namespace App\Services\Communications;

use App\Enums\CommunicationChannel;
use App\Mail\CommunicationMail;
use App\Models\Communication;
use App\Models\Team;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\Traits\ConfiguresTeamMail;
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

        $this->configureMailForTeam($team, false);

        Mail::to($communication->recipient_email, $communication->recipient_name)
            ->send(new CommunicationMail($communication));
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
