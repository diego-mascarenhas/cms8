<?php

namespace App\Services\Mail;

use App\Mail\TestMessageMail;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use App\Support\MessageTemplateMergeFields;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use stdClass;

class MessageCampaignTestSendService
{
    use ConfiguresTeamMail;

    /**
     * @param  list<string>  $recipientEmails
     */
    public function send(Message $message, User $user, Team $team, array $recipientEmails): void
    {
        if (! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }

        $this->configureMailForTeam($team, forMailerCampaigns: true);
        $sender = $message->resolvedMailerSender($team);
        if ($sender['from_name'] !== '')
        {
            Config::set('mail.from.name', $sender['from_name']);
        }
        if ($sender['from_address'] !== '')
        {
            Config::set('mail.from.address', $sender['from_address']);
        }

        foreach ($recipientEmails as $recipientEmail)
        {
            $testContact = new stdClass;
            $testContact->name = (string) Str::of($recipientEmail)->before('@') ?: $user->name;
            $testContact->surname = '';
            $testContact->email = $recipientEmail;
            $testContact->id = 'test';

            $htmlContent = $this->buildHtml($message, $testContact);

            Mail::to($recipientEmail)->send(new TestMessageMail($message, $testContact, $htmlContent));
        }
    }

    private function buildHtml(Message $message, object $testContact): string
    {
        $templateHtml = $message->resolveMailHtml();

        if (trim($templateHtml) === '')
        {
            $templateHtml = '<p>'.e($message->text ?? '').'</p>';
        }

        return MessageTemplateMergeFields::replace($templateHtml, $testContact);
    }
}
