<?php

namespace App\Support;

final class PerformanceDigestReplyParser
{
    /**
     * @return array{subject: string, body: string}
     */
    public static function parseEmailSuggestion(string $suggestion): array
    {
        $suggestion = trim($suggestion);
        if ($suggestion === '')
        {
            return ['subject' => '', 'body' => ''];
        }

        if (preg_match('/^(?:Asunto|Subject):\s*(.+?)(?:\R\R|\n\n)/u', $suggestion, $matches))
        {
            $subject = trim($matches[1]);
            $body = trim(substr($suggestion, strlen($matches[0])));
            $body = preg_replace('/\R\R(?:Saludos,|Best regards,)\s*$/u', '', $body) ?? $body;

            return [
                'subject' => $subject,
                'body' => trim($body),
            ];
        }

        return ['subject' => '', 'body' => $suggestion];
    }
}
