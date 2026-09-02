<?php

namespace App\Services;

use App\Models\Module;
use App\Models\TokenUsageLog;
use App\Support\AiTasks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AiManager;

use function Laravel\Ai\agent;

class DocumentAiOcrService
{
    public function extractTextFromLocalFile(string $absolutePath, ?int $teamId = null): ?string
    {
        return $this->extractWithUsage($absolutePath, $teamId)['text'];
    }

    /**
     * @return array{text: ?string, usage: ?array{model: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}}
     */
    public function extractWithUsage(string $absolutePath, ?int $teamId = null): array
    {
        if (! is_file($absolutePath))
        {
            return ['text' => null, 'usage' => null];
        }

        $ocrPrompt = 'Extract all visible text from this document or spare-part photo. Return only plain text, preserving line breaks. Include brand names, part numbers, OEM codes, and references exactly as printed.';
        $attempts = [
            ['model' => $this->resolveOcrModel(), 'provider' => $this->providerFor($this->resolveOcrModel())],
        ];
        $failover = $this->resolveOcrFailoverModel();
        if ($failover !== '' && $failover !== $attempts[0]['model'])
        {
            $attempts[] = ['model' => $failover, 'provider' => $this->providerFor($failover)];
        }

        $lastError = null;
        foreach ($attempts as $index => $attempt)
        {
            try
            {
                $uploadedFile = new UploadedFile(
                    $absolutePath,
                    basename($absolutePath),
                    mime_content_type($absolutePath) ?: null,
                    null,
                    true,
                );
                $ocrAgent = agent(
                    instructions: 'You are an OCR engine. Return only extracted text.',
                    messages: [],
                    tools: [],
                );
                $response = $ocrAgent->prompt($ocrPrompt, [$uploadedFile], $attempt['provider'], $attempt['model']);
                $text = trim((string) ($response->text ?? ''));
                $this->logTokenUsage($response, $teamId, $ocrPrompt, $text);

                return [
                    'text' => $text !== '' ? $text : null,
                    'usage' => $this->usageFromResponse($response, $attempt['model']),
                ];
            } catch (\Throwable $e)
            {
                $lastError = $e;
                Log::warning('AI OCR extraction failed', [
                    'error' => $e->getMessage(),
                    'path' => $absolutePath,
                    'model' => $attempt['model'],
                    'provider' => $attempt['provider'],
                    'attempt' => $index + 1,
                ]);
            }
        }

        if ($lastError !== null)
        {
            Log::warning('AI OCR extraction exhausted fallbacks', [
                'error' => $lastError->getMessage(),
                'path' => $absolutePath,
            ]);
        }

        return ['text' => null, 'usage' => null];
    }

    public function resolveOcrModel(): string
    {
        $configured = trim((string) config('ai.ocr_model', 'openai/gpt-4o-mini'));
        if ($configured !== '' && strtolower($configured) !== 'cheapest')
        {
            return $configured;
        }

        $provider = AiTasks::provider('ocr');
        $primary = is_array($provider) ? (string) ($provider[0] ?? 'openai') : $provider;

        try
        {
            $cheapest = app(AiManager::class)->textProvider($primary)->cheapestTextModel();
            if (is_string($cheapest) && trim($cheapest) !== '')
            {
                return trim($cheapest);
            }
        } catch (\Throwable)
        {
        }

        return 'openai/gpt-4o-mini';
    }

    public function resolveOcrFailoverModel(): string
    {
        return trim((string) config('ai.ocr_failover_model', 'anthropic/claude-haiku-4.5'));
    }

    /**
     * @return array{model: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}|null
     */
    private function usageFromResponse(mixed $response, string $model): ?array
    {
        $usage = $response->usage ?? null;
        $prompt = is_array($usage)
            ? (int) ($usage['prompt_tokens'] ?? $usage['promptTokens'] ?? 0)
            : (int) ($usage->promptTokens ?? $usage->prompt_tokens ?? 0);
        $completion = is_array($usage)
            ? (int) ($usage['completion_tokens'] ?? $usage['completionTokens'] ?? 0)
            : (int) ($usage->completionTokens ?? $usage->completion_tokens ?? 0);
        $total = $prompt + $completion;
        if ($total <= 0)
        {
            return null;
        }

        return [
            'model' => $model,
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
        ];
    }

    private function providerFor(string $model): string
    {
        if (str_contains($model, '/'))
        {
            return strtolower(explode('/', $model, 2)[0]);
        }

        $provider = AiTasks::provider('ocr');

        return is_array($provider) ? (string) ($provider[0] ?? 'openai') : $provider;
    }

    private function logTokenUsage(mixed $response, ?int $teamId, string $input, string $output): void
    {
        if ($teamId === null)
        {
            return;
        }

        $promptTokens = (int) (($response->usage->promptTokens ?? 0) ?: 0);
        $completionTokens = (int) (($response->usage->completionTokens ?? 0) ?: 0);
        $totalTokens = $promptTokens + $completionTokens;
        if ($totalTokens <= 0)
        {
            return;
        }

        try
        {
            $ocrModuleId = Module::query()->where('key', 'ocr')->value('id');

            TokenUsageLog::withoutGlobalScopes()->create([
                'team_id' => $teamId,
                'module_id' => $ocrModuleId ?? TokenUsageLog::inferModuleId(),
                'service' => 'DocumentAiOcrService',
                'json_size' => strlen($input),
                'toon_size' => strlen($output),
                'json_tokens' => $totalTokens,
                'toon_tokens' => 0,
                'savings_percentage' => 0,
                'used_toon' => false,
            ]);
        } catch (\Throwable $e)
        {
            Log::warning('AI OCR token usage log failed', [
                'error' => $e->getMessage(),
                'team_id' => $teamId,
            ]);
        }
    }
}
