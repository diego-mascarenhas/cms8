<?php

namespace App\Services;

use App\Models\Module;
use App\Models\TokenUsageLog;
use App\Support\AiTasks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

class DocumentAiOcrService
{
    public function extractTextFromLocalFile(string $absolutePath, ?int $teamId = null): ?string
    {
        if (! is_file($absolutePath))
        {
            return null;
        }

        try
        {
            $uploadedFile = new UploadedFile(
                $absolutePath,
                basename($absolutePath),
                mime_content_type($absolutePath) ?: null,
                null,
                true,
            );

            $ocrPrompt = 'Extract all visible text from this document. Return only plain text, preserving line breaks.';
            $ocrAgent = agent(
                instructions: 'You are an OCR engine. Return only extracted text.',
                messages: [],
                tools: [],
            );
            $response = $ocrAgent->prompt($ocrPrompt, [$uploadedFile], provider: AiTasks::provider('ocr'));
            $text = trim((string) ($response->text ?? ''));
            $this->logTokenUsage($response, $teamId, $ocrPrompt, $text);

            return $text !== '' ? $text : null;
        } catch (\Throwable $e)
        {
            Log::warning('AI OCR extraction failed', [
                'error' => $e->getMessage(),
                'path' => $absolutePath,
            ]);

            return null;
        }
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
