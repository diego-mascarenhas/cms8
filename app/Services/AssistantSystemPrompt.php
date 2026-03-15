<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Central place for the "current" system prompt used by the chat assistant and WhatsApp auto-reply.
 * ClaudePromptController can set it via activate(); otherwise falls back to default file or config.
 */
class AssistantSystemPrompt
{
    public const CACHE_KEY = 'assistant_system_prompt';

    public const CACHE_TTL_SECONDS = 86400; // 24 hours

    /**
     * Get the current system prompt (for chat/WhatsApp assistant).
     */
    public static function get(): string
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_string($cached) && $cached !== '')
        {
            return $cached;
        }

        $path = storage_path('app/claude_prompts/default.txt');

        if (File::isFile($path))
        {
            $content = File::get($path);

            if ($content !== '')
            {
                return trim($content);
            }
        }

        return (string) config('anthropic.system_prompt', 'You are a helpful, friendly, and professional assistant. Be concise and clear.');
    }

    /**
     * Set the current system prompt (used by ClaudePromptController::activate).
     */
    public static function set(string $content): void
    {
        Cache::put(self::CACHE_KEY, trim($content), self::CACHE_TTL_SECONDS);
    }

    /**
     * Reset to default (clear cache so get() falls back to file/config).
     */
    public static function reset(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
