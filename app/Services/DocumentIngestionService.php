<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\DocumentIngestion;
use App\Models\Source;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DocumentIngestionService
{
    public function ingestFromConversationMedia(
        Conversation $conversation,
        string $sourceName,
        ?string $sourceReference = null,
        ?int $teamId = null,
    ): array {
        $mediaItems = $conversation->media;
        if (! is_array($mediaItems) || $mediaItems === [])
        {
            return [];
        }

        $resolvedSourceId = $this->resolveSourceIdByName($sourceName);
        $records = [];

        foreach ($mediaItems as $mediaItem)
        {
            if (! is_array($mediaItem))
            {
                continue;
            }

            $fileUrl = trim((string) Arr::get($mediaItem, 'url', ''));
            $mimeType = trim((string) Arr::get($mediaItem, 'content_type', ''));
            $fileName = $this->extractFileName($fileUrl);
            if ($fileName === '')
            {
                $fileName = 'attachment-'.($conversation->id ?? 'unknown').'-'.(count($records) + 1);
            }
            $classification = $this->classifyDocument($mimeType, $fileName, $fileUrl);
            $hashMaterial = implode('|', [$sourceReference ?? '', $fileUrl, $mimeType, $fileName]);

            $records[] = DocumentIngestion::create([
                'team_id' => $teamId,
                'source_id' => $resolvedSourceId,
                'conversation_id' => $conversation->id,
                'source_reference' => $sourceReference,
                'file_name' => $fileName,
                'file_url' => $fileUrl !== '' ? $fileUrl : null,
                'mime_type' => $mimeType !== '' ? Str::lower($mimeType) : null,
                'file_hash' => hash('sha256', $hashMaterial),
                'document_type' => $classification['document_type'],
                'classification_status' => $classification['classification_status'],
                'classification_confidence' => $classification['classification_confidence'],
                'classification_meta' => [
                    'reason' => $classification['reason'],
                    'channel' => Str::lower($sourceName),
                ],
            ]);
        }

        return $records;
    }

    private function resolveSourceIdByName(string $sourceName): ?int
    {
        $source = Source::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($sourceName)])
            ->first();

        return $source?->id;
    }

    /**
     * @return array{document_type:string,classification_status:string,classification_confidence:float,reason:string}
     */
    private function classifyDocument(string $mimeType, string $fileName, string $fileUrl): array
    {
        $mime = Str::lower($mimeType);
        $name = Str::lower($fileName !== '' ? $fileName : basename(parse_url($fileUrl, PHP_URL_PATH) ?: ''));

        if (Str::contains($mime, 'csv') || Str::endsWith($name, '.csv'))
        {
            return [
                'document_type' => 'invoice',
                'classification_status' => 'classified',
                'classification_confidence' => 0.70,
                'reason' => 'Detected CSV tabular document, defaulting to invoice import.',
            ];
        }

        if (Str::contains($mime, 'pdf') || Str::endsWith($name, '.pdf'))
        {
            if ($this->containsAny($name, ['pago', 'payment', 'transfer', 'comprobante', 'receipt']))
            {
                return [
                    'document_type' => 'payment_proof',
                    'classification_status' => 'classified',
                    'classification_confidence' => 0.82,
                    'reason' => 'PDF filename indicates payment proof.',
                ];
            }

            return [
                'document_type' => 'invoice',
                'classification_status' => 'classified',
                'classification_confidence' => 0.78,
                'reason' => 'PDF defaults to invoice when no payment keywords are present.',
            ];
        }

        if (Str::startsWith($mime, 'image/'))
        {
            if ($this->containsAny($name, ['card', 'tarjeta', 'business']))
            {
                return [
                    'document_type' => 'business_card',
                    'classification_status' => 'classified',
                    'classification_confidence' => 0.74,
                    'reason' => 'Image filename indicates business card.',
                ];
            }

            return [
                'document_type' => 'unknown',
                'classification_status' => 'needs_review',
                'classification_confidence' => 0.35,
                'reason' => 'Generic image requires OCR/AI review before entity creation.',
            ];
        }

        return [
            'document_type' => 'unknown',
            'classification_status' => 'needs_review',
            'classification_confidence' => 0.20,
            'reason' => 'Unsupported document type for automatic classification.',
        ];
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle)
        {
            if (Str::contains($haystack, $needle))
            {
                return true;
            }
        }

        return false;
    }

    private function extractFileName(string $fileUrl): string
    {
        $path = (string) parse_url($fileUrl, PHP_URL_PATH);
        $fileName = basename($path);

        return $fileName === '/' || $fileName === '.' ? '' : urldecode($fileName);
    }
}
