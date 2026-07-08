<?php

namespace App\Support;

/**
 * Resolves the AI provider (and failover chain) for a given task from config,
 * so services declare *what* they do instead of hardcoding a provider.
 *
 * Usage:
 *   $agent->prompt($text, [], AiTasks::provider('insight'));
 *
 * When a failover chain is configured, laravel/ai automatically falls over to
 * the next provider if the primary throws a FailoverableException (overloaded
 * or rate limited).
 */
class AiTasks
{
    /**
     * The provider argument to pass to Agent::prompt() for a task: a single
     * provider string when no failover is configured, or an ordered array
     * [primary, ...failover] when it is.
     *
     * @return string|array<int, string>
     */
    public static function provider(string $task): string|array
    {
        $primary = (string) (
            config("ai.tasks.{$task}.provider")
            ?? config('ai.default_task_provider', 'anthropic')
        );

        $failover = config("ai.tasks.{$task}.failover", config('ai.tasks_failover', []));
        $failover = is_array($failover)
            ? array_values(array_filter(array_map('strval', $failover), fn (string $p): bool => $p !== '' && $p !== $primary))
            : [];

        return $failover === [] ? $primary : array_merge([$primary], $failover);
    }
}
