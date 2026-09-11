<?php

namespace App\Support;

use Laravel\Ai\AiManager;

/**
 * Resolves the AI provider (and failover chain) for a given task from config,
 * so services declare *what* they do instead of hardcoding a provider.
 *
 * Usage:
 *   $agent->prompt($text, [], AiTasks::provider('insight'), AiTasks::model('insight'));
 *
 * When a failover chain is configured, laravel/ai automatically falls over to
 * the next provider if the primary throws a FailoverableException (overloaded
 * or rate limited). Providers without an API key are skipped.
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
        $chain = self::providerChain($task);

        if ($chain === [])
        {
            return self::configuredPrimary($task);
        }

        return count($chain) === 1 ? $chain[0] : $chain;
    }

    /**
     * Resolve the model for a task. "cheapest" maps to the selected provider's cheapest text model.
     */
    public static function model(string $task): ?string
    {
        $chain = self::providerChain($task);
        $provider = $chain[0] ?? self::configuredPrimary($task);
        $configured = config("ai.tasks.{$task}.model")
            ?? config('ai.default_task_model', 'cheapest');

        return self::resolveModel($provider, $configured);
    }

    /**
     * @return list<string>
     */
    private static function providerChain(string $task): array
    {
        $primary = self::configuredPrimary($task);
        $failover = config("ai.tasks.{$task}.failover");
        if ($failover === null)
        {
            $failover = config('ai.tasks_failover', []);
        }

        $failover = is_array($failover)
            ? array_values(array_filter(array_map('strval', $failover), fn (string $p): bool => $p !== '' && $p !== $primary))
            : [];

        $candidates = array_merge([$primary], $failover);

        return array_values(array_filter($candidates, fn (string $p): bool => self::hasProviderKey($p)));
    }

    private static function configuredPrimary(string $task): string
    {
        return (string) (
            config("ai.tasks.{$task}.provider")
            ?? config('ai.default_task_provider', 'anthropic')
        );
    }

    private static function hasProviderKey(string $provider): bool
    {
        $key = config("ai.providers.{$provider}.key");

        return is_string($key) && trim($key) !== '';
    }

    private static function resolveModel(string $provider, mixed $configuredModel): ?string
    {
        $model = is_string($configuredModel) ? trim($configuredModel) : null;
        if ($model === null || $model === '')
        {
            return null;
        }

        if (strtolower($model) !== 'cheapest')
        {
            return $model;
        }

        try
        {
            $cheapest = app(AiManager::class)->textProvider($provider)->cheapestTextModel();

            return is_string($cheapest) && trim($cheapest) !== '' ? trim($cheapest) : null;
        } catch (\Throwable)
        {
            return null;
        }
    }
}
