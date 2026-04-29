<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

class DocumentAiOcrService
{
    public function extractTextFromLocalFile(string $absolutePath): ?string
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
            $response = $ocrAgent->prompt($ocrPrompt, [$uploadedFile], provider: 'anthropic');
            $text = trim((string) ($response->text ?? ''));

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
}
