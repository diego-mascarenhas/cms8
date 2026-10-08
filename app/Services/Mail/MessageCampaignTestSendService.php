<?php

namespace App\Services\Mail;

use App\Mail\TestMessageMail;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use App\Services\MailBabyService;
use App\Support\MessageTemplateMergeFields;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
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

            if ($this->sendTestViaMailBaby($message, $sender, $recipientEmail, $htmlContent))
            {
                continue;
            }

            Mail::to($recipientEmail)->send(new TestMessageMail($message, $testContact, $htmlContent));
        }
    }

    /**
     * @param  array{from_name: string, from_address: string}  $sender
     */
    private function sendTestViaMailBaby(Message $message, array $sender, string $recipientEmail, string $htmlContent): bool
    {
        if (! config('services.mailbaby.enabled') || ! config('services.mailbaby.api_key'))
        {
            return false;
        }

        $fromEmail = $sender['from_address'];
        $fromName = $sender['from_name'];
        if ($fromEmail === '')
        {
            return false;
        }

        $from = $fromName !== '' ? $fromName.' <'.$fromEmail.'>' : $fromEmail;

        try
        {
            $result = app(MailBabyService::class)->sendEmail([
                'to' => $recipientEmail,
                'from' => $from,
                'subject' => '[TEST] '.$message->name,
                'body' => $htmlContent,
            ]);
        } catch (\Exception $exception)
        {
            Log::warning('MailBaby API failed for test send', [
                'message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);
            $result = ['success' => false];
        }

        if ($result['success'] ?? false)
        {
            return true;
        }

        if (! config('services.email.fallback_to_smtp', true))
        {
            throw new \RuntimeException('MailBaby API request failed: '.($result['error'] ?? 'Unknown error'));
        }

        return false;
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
