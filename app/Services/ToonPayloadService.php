<?php

namespace App\Services;

use Sbsaga\Toon\Facades\Toon;
use Throwable;

final class ToonPayloadService
{
    /**
     * @return array{
     *     text: string,
     *     used_toon: bool,
     *     json_size: int,
     *     toon_size: int,
     *     json_tokens: int,
     *     toon_tokens: int,
     *     savings_percentage: float,
     *     tokens_saved: int
     * }
     */
    public static function encode(mixed $data): array
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        $jsonSize = strlen($json);
        $jsonTokens = self::estimateTokens($json);

        try
        {
            $toon = Toon::encode($data);
        } catch (Throwable)
        {
            return self::metrics($json !== '' ? $json : '', false, $jsonSize, $jsonSize, $jsonTokens, $jsonTokens);
        }

        $toonSize = strlen($toon);
        $toonTokens = self::estimateTokens($toon);

        if ($toon === '' || $toonTokens >= $jsonTokens)
        {
            return self::metrics($json !== '' ? $json : $toon, false, $jsonSize, $jsonSize, $jsonTokens, $jsonTokens);
        }

        return self::metrics($toon, true, $jsonSize, $toonSize, $jsonTokens, $toonTokens);
    }

    /**
     * @return array{
     *     used_toon: bool,
     *     json_size: int,
     *     toon_size: int,
     *     json_tokens: int,
     *     toon_tokens: int,
     *     savings_percentage: float,
     *     tokens_saved: int
     * }
     */
    public static function emptyMetrics(): array
    {
        return [
            'used_toon' => false,
            'json_size' => 0,
            'toon_size' => 0,
            'json_tokens' => 0,
            'toon_tokens' => 0,
            'savings_percentage' => 0.0,
            'tokens_saved' => 0,
        ];
    }

    /**
     * @param  array{used_toon?: bool, json_size?: int, toon_size?: int, json_tokens?: int, toon_tokens?: int, savings_percentage?: float, tokens_saved?: int}  $left
     * @param  array{used_toon?: bool, json_size?: int, toon_size?: int, json_tokens?: int, toon_tokens?: int, savings_percentage?: float, tokens_saved?: int}  $right
     * @return array{used_toon: bool, json_size: int, toon_size: int, json_tokens: int, toon_tokens: int, savings_percentage: float, tokens_saved: int}
     */
    public static function merge(array $left, array $right): array
    {
        $jsonSize = (int) ($left['json_size'] ?? 0) + (int) ($right['json_size'] ?? 0);
        $toonSize = (int) ($left['toon_size'] ?? 0) + (int) ($right['toon_size'] ?? 0);
        $jsonTokens = (int) ($left['json_tokens'] ?? 0) + (int) ($right['json_tokens'] ?? 0);
        $toonTokens = (int) ($left['toon_tokens'] ?? 0) + (int) ($right['toon_tokens'] ?? 0);
        $saved = max(0, $jsonTokens - $toonTokens);

        return [
            'used_toon' => (bool) ($left['used_toon'] ?? false) || (bool) ($right['used_toon'] ?? false),
            'json_size' => $jsonSize,
            'toon_size' => $toonSize,
            'json_tokens' => $jsonTokens,
            'toon_tokens' => $toonTokens,
            'savings_percentage' => $jsonTokens > 0 ? round(min(100, ($saved / $jsonTokens) * 100), 2) : 0.0,
            'tokens_saved' => $saved,
        ];
    }

    public static function estimateTokens(string $text): int
    {
        if ($text === '')
        {
            return 0;
        }

        return (int) max(1, (int) round(strlen($text) / 4));
    }

    /**
     * @return array{
     *     text: string,
     *     used_toon: bool,
     *     json_size: int,
     *     toon_size: int,
     *     json_tokens: int,
     *     toon_tokens: int,
     *     savings_percentage: float,
     *     tokens_saved: int
     * }
     */
    private static function metrics(
        string $text,
        bool $usedToon,
        int $jsonSize,
        int $toonSize,
        int $jsonTokens,
        int $toonTokens,
    ): array {
        $saved = max(0, $jsonTokens - $toonTokens);

        return [
            'text' => $text,
            'used_toon' => $usedToon,
            'json_size' => $jsonSize,
            'toon_size' => $toonSize,
            'json_tokens' => $jsonTokens,
            'toon_tokens' => $toonTokens,
            'savings_percentage' => $jsonTokens > 0 ? round(min(100, ($saved / $jsonTokens) * 100), 2) : 0.0,
            'tokens_saved' => $saved,
        ];
    }
}
