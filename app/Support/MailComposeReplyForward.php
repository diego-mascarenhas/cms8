<?php

namespace App\Support;

final class MailComposeReplyForward
{
    public static function extractEmailAddress(string $address): string
    {
        $address = trim($address);

        if (preg_match('/<([^>]+)>/', $address, $matches))
        {
            return strtolower(trim($matches[1]));
        }

        return strtolower($address);
    }

    /**
     * @param  array<string, mixed>  $email
     * @return array{recipients: list<string>, subject: string, body: string}
     */
    public static function replyPayload(array $email, string $folder = 'inbox'): array
    {
        $recipient = self::replyRecipient($email, $folder);

        return [
            'recipients' => $recipient !== '' ? [$recipient] : [],
            'subject' => self::prefixedSubject((string) ($email['subject'] ?? ''), 'Re:'),
            'body' => self::quotedBody($email),
        ];
    }

    /**
     * @param  array<string, mixed>  $email
     * @return array{recipients: list<string>, subject: string, body: string}
     */
    public static function forwardPayload(array $email): array
    {
        return [
            'recipients' => [],
            'subject' => self::prefixedSubject((string) ($email['subject'] ?? ''), 'Fwd:'),
            'body' => self::forwardedBody($email),
        ];
    }

    /**
     * @param  array<string, mixed>  $email
     */
    private static function replyRecipient(array $email, string $folder): string
    {
        if ($folder === 'sent')
        {
            return self::extractEmailAddress((string) ($email['to'] ?? ''));
        }

        return self::extractEmailAddress((string) ($email['from'] ?? ''));
    }

    private static function prefixedSubject(string $subject, string $prefix): string
    {
        $subject = trim($subject);

        if ($subject === '')
        {
            return $prefix;
        }

        if (preg_match('/^'.preg_quote($prefix, '/').'\s+/i', $subject))
        {
            return $subject;
        }

        return $prefix.' '.$subject;
    }

    /**
     * @param  array<string, mixed>  $email
     */
    private static function quotedBody(array $email): string
    {
        $body = self::plainBody((string) ($email['body'] ?? ''));

        return "\n\n".__('---------- Original message ----------')
            ."\n".__('From:').' '.trim((string) ($email['from'] ?? ''))
            ."\n".__('Date:').' '.trim((string) ($email['date_display'] ?? ''))
            ."\n".__('Subject:').' '.trim((string) ($email['subject'] ?? ''))
            ."\n\n".$body;
    }

    /**
     * @param  array<string, mixed>  $email
     */
    private static function forwardedBody(array $email): string
    {
        $body = self::plainBody((string) ($email['body'] ?? ''));

        return "\n\n".__('---------- Forwarded message ----------')
            ."\n".__('From:').' '.trim((string) ($email['from'] ?? ''))
            ."\n".__('Date:').' '.trim((string) ($email['date_display'] ?? ''))
            ."\n".__('Subject:').' '.trim((string) ($email['subject'] ?? ''))
            ."\n\n".$body;
    }

    private static function plainBody(string $body): string
    {
        $body = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $body;
    }
}
