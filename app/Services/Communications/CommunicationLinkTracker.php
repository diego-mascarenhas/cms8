<?php

namespace App\Services\Communications;

use App\Models\Communication;

class CommunicationLinkTracker
{
    public function render(Communication $communication): string
    {
        $html = nl2br(e((string) $communication->message));
        $html = $this->autolink($html);
        $html = $this->rewriteAnchors($html, $communication);

        return $html;
    }

    public function autolink(string $html): string
    {
        return (string) preg_replace_callback(
            '/(?<!["\'])(https?:\/\/[^\s<]+)/i',
            function (array $matches): string
            {
                $raw = $matches[1];
                $trailing = '';
                if (preg_match('/^(.*?)([.,;:!?)]+)$/', $raw, $parts) === 1)
                {
                    $raw = $parts[1];
                    $trailing = $parts[2];
                }

                if (! $this->isSafeHttpUrl($raw))
                {
                    return $matches[0];
                }

                return '<a href="'.e($raw).'">'.e($raw).'</a>'.$trailing;
            },
            $html,
        );
    }

    public function rewriteAnchors(string $html, Communication $communication): string
    {
        return (string) preg_replace_callback(
            '/(<a\b[^>]*\bhref=["\'])([^"\']+)(["\'][^>]*>)/i',
            function (array $matches) use ($communication): string
            {
                $url = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5);

                if (! $this->isSafeHttpUrl($url) || str_contains($url, '/communications/track/'))
                {
                    return $matches[0];
                }

                return $matches[1].e($communication->clickTrackingUrl($url)).$matches[3];
            },
            $html,
        );
    }

    public function isSafeHttpUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts))
        {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        return in_array($scheme, ['http', 'https'], true) && $host !== '';
    }
}
