<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenRouterModelCatalog
{
    public const CACHE_KEY = 'openrouter.models.catalog';

    /**
     * @var array<string, array{prompt: float, completion: float}>
     */
    private const FALLBACKS = [
        'claude-haiku-4.5' => ['prompt' => 1.0, 'completion' => 5.0],
        'claude-sonnet-4.5' => ['prompt' => 3.0, 'completion' => 15.0],
        'whisper-1' => ['prompt' => 6.0, 'completion' => 6.0],
        'gpt-4o-mini' => ['prompt' => 0.15, 'completion' => 0.6],
    ];

    /**
     * @return array{prompt_per_million: float, completion_per_million: float, id: string, name: string}|null
     */
    public function find(string $used): ?array
    {
        $wanted = $this->normalizeKey($used);
        if ($wanted === '')
        {
            return null;
        }

        $best = null;
        $bestScore = 0;
        foreach ($this->models() as $model)
        {
            $score = $this->score($wanted, $model);
            if ($score > $bestScore)
            {
                $bestScore = $score;
                $best = $model;
            }
        }

        if ($best !== null)
        {
            return $best;
        }

        $fallback = self::FALLBACKS[$wanted] ?? null;
        if ($fallback === null)
        {
            return null;
        }

        return [
            'id' => $used,
            'name' => $used,
            'prompt_per_million' => $fallback['prompt'],
            'completion_per_million' => $fallback['completion'],
        ];
    }

    /**
     * @return list<array{id: string, name: string, prompt_per_million: float, completion_per_million: float}>
     */
    public function models(): array
    {
        $ttl = (int) config('services.openrouter.cache_seconds', 3600);

        try
        {
            return $this->cache()->remember(self::CACHE_KEY, $ttl, function (): array
            {
                return $this->fetchModels();
            });
        } catch (Throwable $exception)
        {
            Log::warning('Failed to load OpenRouter models', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return list<array{id: string, name: string, prompt_per_million: float, completion_per_million: float}>
     */
    public function fetchModels(): array
    {
        $url = (string) config('services.openrouter.models_url', 'https://openrouter.ai/api/v1/models');
        $response = Http::acceptJson()->timeout(20)->get($url);
        if ($response->failed())
        {
            throw new RequestException($response);
        }

        /** @var list<array<string, mixed>> $payload */
        $payload = $response->json('data', []);

        return collect($payload)
            ->map(fn (array $model): array => $this->normalize($model))
            ->filter(fn (array $model): bool => $model['id'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $model
     * @return array{id: string, name: string, prompt_per_million: float, completion_per_million: float}
     */
    public function normalize(array $model): array
    {
        $pricing = is_array($model['pricing'] ?? null) ? $model['pricing'] : [];

        return [
            'id' => (string) ($model['id'] ?? ''),
            'name' => (string) ($model['name'] ?? $model['id'] ?? 'Unknown'),
            'prompt_per_million' => $this->dollarsPerMillion($pricing['prompt'] ?? null) ?? 0.0,
            'completion_per_million' => $this->dollarsPerMillion($pricing['completion'] ?? null) ?? 0.0,
        ];
    }

    public function normalizeKey(string $value): string
    {
        $key = strtolower(trim($value));
        $key = preg_replace('/^~/', '', $key) ?? $key;
        $key = preg_replace('/:\w+$/', '', $key) ?? $key;
        $key = preg_replace('/^[^\/]+\//', '', $key) ?? $key;
        $key = preg_replace('/-\d{8}$/', '', $key) ?? $key;
        $key = str_replace('_', '-', $key);

        return preg_replace('/(\d)-(\d)/', '$1.$2', $key) ?? $key;
    }

    public function dollarsPerMillion(mixed $pricePerToken): ?float
    {
        if ($pricePerToken === null || $pricePerToken === '')
        {
            return null;
        }

        if (! is_numeric($pricePerToken))
        {
            return null;
        }

        return round((float) $pricePerToken * 1_000_000, 4);
    }

    /**
     * @param  array{id: string, name: string, prompt_per_million: float, completion_per_million: float}  $model
     */
    private function score(string $wanted, array $model): int
    {
        $idKey = $this->normalizeKey($model['id']);
        $nameKey = $this->normalizeKey($model['name']);
        if ($idKey !== $wanted && $nameKey !== $wanted && ! str_ends_with($idKey, '/'.$wanted))
        {
            return 0;
        }

        $score = ($idKey === $wanted || str_ends_with($idKey, '/'.$wanted)) ? 100 : 60;
        if (str_contains($model['id'], ':'))
        {
            $score -= 40;
        }

        return $score;
    }

    private function cache(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store((string) config('services.openrouter.cache_store', 'file'));
    }
}
