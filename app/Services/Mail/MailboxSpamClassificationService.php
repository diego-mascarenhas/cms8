<?php

namespace App\Services\Mail;

use App\Enums\EmailFolder;
use App\Models\Email;
use App\Models\Team;
use App\Services\ChatAssistantReplyService;
use Illuminate\Support\Facades\Log;

class MailboxSpamClassificationService
{
    public function __construct(
        private ChatAssistantReplyService $replyService,
    ) {}

    public function isEnabledForTeam(Team $team): bool
    {
        return filter_var($team->getSetting('mailbox_spam_ai_enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    public function classifyAndApply(Email $email): bool
    {
        $team = $email->team;
        if (! $team || ! $this->isEnabledForTeam($team))
        {
            return false;
        }

        if ($email->folder !== EmailFolder::Inbox)
        {
            return false;
        }

        $isSpam = $this->classify($email, $team);
        if ($isSpam)
        {
            $email->update(['folder' => EmailFolder::Spam->value]);
        }

        return $isSpam;
    }

    public function classify(Email $email, Team $team): bool
    {
        $customPrompt = trim((string) $team->getSetting('mailbox_spam_ai_prompt', ''));
        $instruction = $customPrompt !== ''
            ? $customPrompt
            : 'You classify inbound business emails as spam or not spam. Reply with JSON only: {"spam": true} or {"spam": false}. Mark as spam only obvious scams, phishing, unsolicited bulk ads, or malicious content.';

        $snippet = mb_substr(strip_tags((string) ($email->body_text ?: $email->body_html ?: '')), 0, 2000);
        $message = implode("\n", array_filter([
            'Subject: '.($email->subject ?? '(no subject)'),
            'From: '.$email->from_address,
            'Body:',
            $snippet !== '' ? $snippet : '(empty body)',
        ]));

        try
        {
            $reply = $this->replyService->getReply(
                $instruction."\n\n".$message,
                [],
                (int) $team->id,
                false,
            );

            if (! ($reply['success'] ?? false))
            {
                return false;
            }

            return $this->parseSpamResponse((string) ($reply['text'] ?? ''));
        } catch (\Throwable $e)
        {
            Log::warning('Mailbox spam classification failed', [
                'email_id' => $email->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function parseSpamResponse(string $text): bool
    {
        $raw = trim($text);
        if ($raw === '')
        {
            return false;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded) && array_key_exists('spam', $decoded))
        {
            return filter_var($decoded['spam'], FILTER_VALIDATE_BOOLEAN);
        }

        if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/m', $raw, $matches))
        {
            $decoded = json_decode(trim($matches[1]), true);
            if (is_array($decoded) && array_key_exists('spam', $decoded))
            {
                return filter_var($decoded['spam'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return str_contains(strtolower($raw), '"spam": true') || str_contains(strtolower($raw), '"spam":true');
    }
}
