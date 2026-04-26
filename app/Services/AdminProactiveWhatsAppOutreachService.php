<?php

namespace App\Services;

use App\Models\Prompt;

/**
 * Parses admin-only "keyword + phone" lines in the assistant chat and resolves the keyword
 * to a team flow routing key for proactive WhatsApp outreach.
 */
class AdminProactiveWhatsAppOutreachService
{
    /**
     * @return array{keyword: string, phone_digits: string}|null
     */
    public function parseKeywordAndPhone(string $message): ?array
    {
        $trim = trim($message);
        if ($trim === '' || str_contains($trim, "\n"))
        {
            return null;
        }

        // Phone tail: optional +, digits with spaces / hyphens / parentheses; normalized to digits below.
        if (! preg_match(
            '/(\+[0-9][+()0-9\s\-]{8,55}|\([+()0-9][+()0-9\s\-]{7,55}|[0-9][+()0-9\s\-]{9,55})\s*$/u',
            $trim,
            $m,
            PREG_OFFSET_CAPTURE,
        ))
        {
            return null;
        }

        $phoneRaw = trim($m[1][0]);
        $offset = (int) $m[0][1];
        $keywordPart = trim(substr($trim, 0, $offset));
        $keywordPart = preg_replace('/\s*:\s*$/u', '', $keywordPart) ?? $keywordPart;
        $keywordPart = trim((string) $keywordPart);

        if ($keywordPart === '' || mb_strlen($keywordPart) > 80)
        {
            return null;
        }

        if (! preg_match('/^[\p{L}\p{N}_\-\s]+$/u', $keywordPart))
        {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $phoneRaw) ?? '';
        if (strlen($digits) < 10 || strlen($digits) > 15)
        {
            return null;
        }

        return [
            'keyword' => $keywordPart,
            'phone_digits' => $digits,
        ];
    }

    /**
     * Match keyword to an active team prompt routing key (section_key, full key, suffix after ":", or label).
     */
    public function resolveRoutingKeyForKeyword(int $teamId, string $keyword): ?string
    {
        $k = mb_strtolower(trim($keyword));
        $kHyphenated = mb_strtolower((string) (preg_replace('/\s+/u', '-', trim($keyword)) ?? trim($keyword)));

        $prompts = Prompt::forTeam($teamId)
            ->active()
            ->with('module')
            ->where('section_key', '!=', 'general')
            ->orderBy('order')
            ->get();

        foreach ($prompts as $prompt)
        {
            /** @var Prompt $prompt */
            $sk = mb_strtolower((string) $prompt->section_key);
            $skNorm = str_replace('_', '-', $sk);
            if ($sk === $k || $skNorm === $kHyphenated)
            {
                return $this->routingKeyForPrompt($prompt);
            }
        }

        foreach ($prompts as $prompt)
        {
            /** @var Prompt $prompt */
            $rk = mb_strtolower($this->routingKeyForPrompt($prompt));
            if ($rk === $k || str_ends_with($rk, ':'.$k))
            {
                return $this->routingKeyForPrompt($prompt);
            }
        }

        foreach ($prompts as $prompt)
        {
            /** @var Prompt $prompt */
            $label = mb_strtolower(trim((string) $prompt->section_label));
            if ($label !== '' && ($label === $k || str_replace(' ', '-', $label) === $kHyphenated))
            {
                return $this->routingKeyForPrompt($prompt);
            }
        }

        return null;
    }

    public function routingKeyForPrompt(Prompt $prompt): string
    {
        $prompt->loadMissing('module');

        return $prompt->module
            ? $prompt->module->key.':'.$prompt->section_key
            : $prompt->section_key;
    }
}
