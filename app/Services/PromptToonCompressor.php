<?php

namespace App\Services;

/**
 * Compresses JSON blocks inside a prompt the client sent uncompressed.
 * The model receives TOON only when that encoding is shorter.
 */
final class PromptToonCompressor
{
    /**
     * @return array{
     *     prompt: string,
     *     toon: array{
     *         used_toon: bool,
     *         json_size: int,
     *         toon_size: int,
     *         json_tokens: int,
     *         toon_tokens: int,
     *         tokens_saved: int,
     *         savings_percentage: int
     *     }
     * }
     */
    public static function compress(string $prompt): array
    {
        $metrics = ToonPayloadService::emptyMetrics();

        $compressed = preg_replace_callback(
            '/```json[ \t]*\r?\n(.*?)```/s',
            function (array $matches) use (&$metrics): string
            {
                $decoded = json_decode(trim($matches[1]), true);
                if (! is_array($decoded))
                {
                    return $matches[0];
                }

                $encoded = ToonPayloadService::encode($decoded);
                $metrics = ToonPayloadService::merge($metrics, $encoded);

                if (! ($encoded['used_toon'] ?? false))
                {
                    return $matches[0];
                }

                return "```toon\n".$encoded['text']."\n```";
            },
            $prompt,
        );

        return [
            'prompt' => is_string($compressed) ? $compressed : $prompt,
            'toon' => self::publicMetrics($metrics),
        ];
    }

    /**
     * @param  array{used_toon?: bool, json_size?: int, toon_size?: int, json_tokens?: int, toon_tokens?: int, tokens_saved?: int, savings_percentage?: float|int}  $metrics
     * @return array{used_toon: bool, json_size: int, toon_size: int, json_tokens: int, toon_tokens: int, tokens_saved: int, savings_percentage: int}
     */
    private static function publicMetrics(array $metrics): array
    {
        return [
            'used_toon' => (bool) ($metrics['used_toon'] ?? false),
            'json_size' => (int) ($metrics['json_size'] ?? 0),
            'toon_size' => (int) ($metrics['toon_size'] ?? 0),
            'json_tokens' => (int) ($metrics['json_tokens'] ?? 0),
            'toon_tokens' => (int) ($metrics['toon_tokens'] ?? 0),
            'tokens_saved' => (int) ($metrics['tokens_saved'] ?? 0),
            'savings_percentage' => (int) round((float) ($metrics['savings_percentage'] ?? 0)),
        ];
    }
}
