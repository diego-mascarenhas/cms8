<?php

namespace App\Services\Mail;

use App\Enums\MailboxInboundKind;
use App\Models\Contact;

class MailboxInboundClassifier
{
    /**
     * @return array{kind: MailboxInboundKind, address: ?string, reason: string}
     */
    public function classify(string $from, string $subject, string $body): array
    {
        $from = trim($from);
        $subject = trim($subject);
        $body = trim($body);

        if ($this->isAutomatic($subject, $body))
        {
            return $this->ignored();
        }

        if (! $this->isDaemon($from) && $this->isHumanReply($subject, $body))
        {
            return [
                'kind' => MailboxInboundKind::Reply,
                'address' => $this->firstEmail($from),
                'reason' => $this->excerpt($body),
            ];
        }

        if ($this->isBounce($from, $subject, $body))
        {
            return $this->bounce($body);
        }

        return $this->ignored();
    }

    /**
     * @return array{kind: MailboxInboundKind, address: ?string, reason: string}
     */
    private function bounce(string $body): array
    {
        $status = $this->statusCode($body);
        $reason = $this->diagnostic($body, $status);
        $address = $this->finalRecipient($body);
        $lower = mb_strtolower($reason.' '.$body);

        if ($this->isPolicy($status, $lower))
        {
            return [
                'kind' => MailboxInboundKind::PolicyBounce,
                'address' => $address,
                'reason' => $reason,
            ];
        }

        if ($this->isSoft($status, $lower))
        {
            return [
                'kind' => MailboxInboundKind::SoftBounce,
                'address' => $address,
                'reason' => $reason,
            ];
        }

        if ($status !== null && (str_starts_with($status, '5.1.') || Contact::emailFailureIsPermanent($reason.' '.$status)))
        {
            return [
                'kind' => MailboxInboundKind::HardBounce,
                'address' => $address,
                'reason' => $reason,
            ];
        }

        if (Contact::emailFailureIsPermanent($lower))
        {
            return [
                'kind' => MailboxInboundKind::HardBounce,
                'address' => $address,
                'reason' => $reason,
            ];
        }

        return [
            'kind' => MailboxInboundKind::SoftBounce,
            'address' => $address,
            'reason' => $reason,
        ];
    }

    private function isDaemon(string $from): bool
    {
        $lower = mb_strtolower($from);

        return str_contains($lower, 'mailer-daemon')
            || str_contains($lower, 'postmaster');
    }

    private function isBounce(string $from, string $subject, string $body): bool
    {
        if ($this->isDaemon($from))
        {
            return true;
        }

        $subjectLower = mb_strtolower($subject);
        foreach ([
            'undeliver',
            'delivery status',
            'delivery failure',
            'mail delivery failed',
            'returned mail',
            'failure notice',
            'no se pudo entregar',
            'fallo de entrega',
        ] as $needle)
        {
            if (str_contains($subjectLower, $needle))
            {
                return true;
            }
        }

        return (bool) preg_match('/^(?:Status|Final-Recipient|Diagnostic-Code):/mi', $body);
    }

    private function isAutomatic(string $subject, string $body): bool
    {
        $subjectLower = mb_strtolower($subject);
        foreach ([
            'out of office',
            'fuera de la oficina',
            'automatic reply',
            'respuesta automática',
            'respuesta automatica',
            'auto-reply',
            'autoreply',
            'ausente de la oficina',
        ] as $needle)
        {
            if (str_contains($subjectLower, $needle))
            {
                return true;
            }
        }

        return (bool) preg_match('/auto-submitted:\s*auto-|precedence:\s*(?:bulk|auto_reply|junk)/i', $body);
    }

    private function isHumanReply(string $subject, string $body): bool
    {
        if (preg_match('/^(?:re|rv|aw|res|respuesta)\s*:/i', $subject) === 1)
        {
            return true;
        }

        return (bool) preg_match('/\b(?:escribió|escribio|wrote)\s*:/iu', $body);
    }

    private function isPolicy(?string $status, string $lower): bool
    {
        if ($status !== null)
        {
            return str_starts_with($status, '5.7.');
        }

        return str_contains($lower, '5.7.1')
            || str_contains($lower, 'spam')
            || str_contains($lower, 'policy')
            || str_contains($lower, 'blocked');
    }

    private function isSoft(?string $status, string $lower): bool
    {
        if ($status !== null)
        {
            return str_starts_with($status, '4.');
        }

        return str_contains($lower, '4.2.2')
            || str_contains($lower, 'mailbox full')
            || str_contains($lower, 'casilla llena')
            || str_contains($lower, 'temporarily')
            || str_contains($lower, 'try again');
    }

    private function statusCode(string $body): ?string
    {
        if (preg_match('/Status:\s*([245]\.\d{1,3}\.\d{1,3})/i', $body, $matches) === 1)
        {
            return $matches[1];
        }

        return null;
    }

    private function diagnostic(string $body, ?string $status): string
    {
        if (preg_match('/Diagnostic-Code:\s*(.+)/i', $body, $matches) === 1)
        {
            return trim($matches[1]);
        }

        if ($status !== null)
        {
            return $status;
        }

        $line = trim((string) strtok($body, "\n"));

        return mb_substr($line, 0, 180);
    }

    private function finalRecipient(string $body): ?string
    {
        if (preg_match('/(?:Final|Original)-Recipient:\s*(?:rfc822;\s*)?<?([^\s<>;]+@[^\s<>;]+)>?/i', $body, $matches) === 1)
        {
            return strtolower($matches[1]);
        }

        if (preg_match('/<([^>\s]+@[^>\s]+)>:/', $body, $matches) === 1)
        {
            return strtolower($matches[1]);
        }

        return $this->firstEmail($body);
    }

    private function firstEmail(string $value): ?string
    {
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches) !== 1)
        {
            return null;
        }

        return strtolower($matches[0]);
    }

    private function excerpt(string $body): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
        if ($text === '')
        {
            return '';
        }

        return mb_substr($text, 0, 240);
    }

    /**
     * @return array{kind: MailboxInboundKind, address: ?string, reason: string}
     */
    private function ignored(): array
    {
        return [
            'kind' => MailboxInboundKind::Ignore,
            'address' => null,
            'reason' => '',
        ];
    }
}
