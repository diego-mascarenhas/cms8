<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Currency;
use App\Models\DocumentIngestion;
use App\Models\Enterprise;
use App\Models\EnterpriseType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceType;
use App\Models\Source;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentIngestionService
{
    public function __construct(
        private readonly DocumentOcrService $ocrService,
        private readonly DocumentAiOcrService $aiOcrService,
    ) {}

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

            $hashMaterial = implode('|', [$sourceReference ?? '', $fileUrl, $mimeType, $fileName]);

            try
            {
                $classification = $this->classifyDocument($mimeType, $fileName, $fileUrl);
                $ocrResult = $this->extractOcrText($fileUrl, $mimeType, $teamId);
                $ocrText = $ocrResult['text'];
                $extractedData = $this->extractStructuredDataFromText($ocrText);
                $classification = $this->refineClassificationWithExtractedData($classification, $extractedData, $mimeType, $fileName);

                $ingestion = DocumentIngestion::create([
                    'team_id' => $teamId,
                    'source_id' => $resolvedSourceId,
                    'conversation_id' => $conversation->id,
                    'source_reference' => $sourceReference,
                    'file_name' => Str::limit($fileName, 255, ''),
                    'file_url' => $fileUrl !== '' ? $fileUrl : null,
                    'mime_type' => $mimeType !== '' ? Str::limit(Str::lower($mimeType), 191, '') : null,
                    'file_hash' => hash('sha256', $hashMaterial),
                    'document_type' => $classification['document_type'],
                    'classification_status' => $classification['classification_status'],
                    'classification_confidence' => $classification['classification_confidence'],
                    'ocr_text' => $ocrText,
                    'extracted_data' => $extractedData !== [] ? $extractedData : null,
                    'classification_meta' => array_filter([
                        'reason' => $classification['reason'],
                        'channel' => Str::lower($sourceName),
                        'ocr_applied' => $ocrText !== null,
                        'ocr_mode' => $ocrResult['mode'],
                        'ocr_engine_used' => $ocrResult['engine_used'],
                        'ocr_engines_ran' => $ocrResult['engines_ran'],
                        'ocr_usage' => $ocrResult['usage'] ?? null,
                    ], fn (mixed $value): bool => $value !== null),
                ]);

                try
                {
                    $this->attachCreatedContactIfApplicable($ingestion, $extractedData, $teamId, $resolvedSourceId);
                } catch (\Throwable $e)
                {
                    $ingestion->update([
                        'classification_meta' => array_merge((array) ($ingestion->classification_meta ?? []), [
                            'contact_attach_error' => Str::limit($e->getMessage(), 1000, ''),
                        ]),
                    ]);
                }

                try
                {
                    $this->attachCreatedInvoiceIfApplicable($ingestion, $extractedData, $teamId, $sourceName);
                } catch (\Throwable $e)
                {
                    $ingestion->update([
                        'classification_meta' => array_merge((array) ($ingestion->classification_meta ?? []), [
                            'invoice_attach_error' => Str::limit($e->getMessage(), 1000, ''),
                        ]),
                    ]);
                }
                $records[] = $ingestion->refresh();
            } catch (\Throwable $e)
            {
                $records[] = DocumentIngestion::create([
                    'team_id' => $teamId,
                    'source_id' => $resolvedSourceId,
                    'conversation_id' => $conversation->id,
                    'source_reference' => $sourceReference,
                    'file_name' => Str::limit($fileName, 255, ''),
                    'file_url' => $fileUrl !== '' ? $fileUrl : null,
                    'mime_type' => $mimeType !== '' ? Str::limit(Str::lower($mimeType), 191, '') : null,
                    'file_hash' => hash('sha256', $hashMaterial),
                    'document_type' => 'unknown',
                    'classification_status' => 'failed',
                    'classification_confidence' => 0,
                    'classification_meta' => [
                        'channel' => Str::lower($sourceName),
                        'failed_step' => 'ingestion',
                    ],
                    'processing_error' => Str::limit($e->getMessage(), 65535, ''),
                    'processed_at' => now(),
                ]);
            }
        }

        return $records;
    }

    public function reprocessDocument(DocumentIngestion $documentIngestion): DocumentIngestion
    {
        $fileUrl = (string) ($documentIngestion->file_url ?? '');
        $mimeType = (string) ($documentIngestion->mime_type ?? '');
        $fileName = (string) ($documentIngestion->file_name ?? '');
        if ($fileName === '')
        {
            $fileName = $this->extractFileName($fileUrl);
        }
        if ($fileName === '')
        {
            $fileName = 'attachment-'.$documentIngestion->id;
        }

        try
        {
            $classification = $this->classifyDocument($mimeType, $fileName, $fileUrl);
            $ocrResult = $this->extractOcrText($fileUrl, $mimeType, $documentIngestion->team_id);
            $ocrText = $ocrResult['text'];
            $extractedData = $this->extractStructuredDataFromText($ocrText);
            $classification = $this->refineClassificationWithExtractedData($classification, $extractedData, $mimeType, $fileName);

            $documentIngestion->fill([
                'file_name' => Str::limit($fileName, 255, ''),
                'mime_type' => $mimeType !== '' ? Str::limit(Str::lower($mimeType), 191, '') : null,
                'document_type' => $classification['document_type'],
                'classification_status' => $classification['classification_status'],
                'classification_confidence' => $classification['classification_confidence'],
                'ocr_text' => $ocrText,
                'extracted_data' => $extractedData !== [] ? $extractedData : null,
                'classification_meta' => array_merge((array) ($documentIngestion->classification_meta ?? []), array_filter([
                    'reason' => $classification['reason'],
                    'ocr_applied' => $ocrText !== null,
                    'ocr_mode' => $ocrResult['mode'],
                    'ocr_engine_used' => $ocrResult['engine_used'],
                    'ocr_engines_ran' => $ocrResult['engines_ran'],
                    'ocr_usage' => $ocrResult['usage'] ?? null,
                    'reprocessed_at' => now()->toIso8601String(),
                ], fn (mixed $value): bool => $value !== null)),
                'processing_error' => null,
                'processed_at' => now(),
            ])->save();
            try
            {
                $this->attachCreatedContactIfApplicable(
                    $documentIngestion,
                    $extractedData,
                    $documentIngestion->team_id,
                    $documentIngestion->source_id,
                );
            } catch (\Throwable $e)
            {
                $documentIngestion->update([
                    'classification_meta' => array_merge((array) ($documentIngestion->classification_meta ?? []), [
                        'contact_attach_error' => Str::limit($e->getMessage(), 1000, ''),
                    ]),
                ]);
            }

            try
            {
                $sourceName = (string) data_get($documentIngestion->classification_meta ?? [], 'channel', '');
                $this->attachCreatedInvoiceIfApplicable(
                    $documentIngestion,
                    $extractedData,
                    $documentIngestion->team_id,
                    $sourceName,
                );
            } catch (\Throwable $e)
            {
                $documentIngestion->update([
                    'classification_meta' => array_merge((array) ($documentIngestion->classification_meta ?? []), [
                        'invoice_attach_error' => Str::limit($e->getMessage(), 1000, ''),
                    ]),
                ]);
            }
        } catch (\Throwable $e)
        {
            $documentIngestion->fill([
                'classification_status' => 'failed',
                'classification_confidence' => 0,
                'processing_error' => Str::limit($e->getMessage(), 65535, ''),
                'processed_at' => now(),
                'classification_meta' => array_merge((array) ($documentIngestion->classification_meta ?? []), [
                    'failed_step' => 'reprocess',
                    'reprocessed_at' => now()->toIso8601String(),
                ]),
            ])->save();
        }

        return $documentIngestion->refresh();
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
        $isCsv = Str::contains($mime, 'csv') || Str::endsWith($name, '.csv');
        $isPdf = Str::contains($mime, 'pdf') || Str::endsWith($name, '.pdf');
        $isImage = Str::startsWith($mime, 'image/')
            || Str::endsWith($name, '.jpg')
            || Str::endsWith($name, '.jpeg')
            || Str::endsWith($name, '.png')
            || Str::endsWith($name, '.webp')
            || Str::endsWith($name, '.gif');

        if ($isCsv)
        {
            return [
                'document_type' => 'invoice',
                'classification_status' => 'classified',
                'classification_confidence' => 0.70,
                'reason' => 'Detected CSV tabular document, defaulting to invoice import.',
            ];
        }

        if ($isPdf)
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

        if ($isImage)
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

    /**
     * @return array{text:?string,mode:string,engine_used:?string,engines_ran:array<int,string>,usage:?array{model: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}}
     */
    private function extractOcrText(string $fileUrl, string $mimeType, ?int $teamId): array
    {
        $lowerMime = Str::lower($mimeType);
        $lowerPath = Str::lower((string) parse_url($fileUrl, PHP_URL_PATH));
        $isImage = Str::startsWith($lowerMime, 'image/')
            || Str::endsWith($lowerPath, '.jpg')
            || Str::endsWith($lowerPath, '.jpeg')
            || Str::endsWith($lowerPath, '.png')
            || Str::endsWith($lowerPath, '.webp')
            || Str::endsWith($lowerPath, '.gif');
        $isPdf = Str::contains($lowerMime, 'pdf') || Str::endsWith($lowerPath, '.pdf');
        if ($fileUrl === '' || (! $isImage && ! $isPdf))
        {
            return [
                'text' => null,
                'mode' => $this->resolveOcrMode($teamId),
                'engine_used' => null,
                'engines_ran' => [],
                'usage' => null,
            ];
        }

        $downloadedPath = null;
        $localPath = $this->resolveLocalPathFromUrl($fileUrl);
        if ($localPath === null)
        {
            $downloadedPath = $this->downloadRemoteFileForOcr($fileUrl);
            $localPath = $downloadedPath;
        }
        if ($localPath === null)
        {
            return [
                'text' => null,
                'mode' => $this->resolveOcrMode($teamId),
                'engine_used' => null,
                'engines_ran' => [],
                'usage' => null,
            ];
        }

        try
        {
            $mode = $this->resolveOcrMode($teamId);
            $localText = null;
            $aiText = null;
            $aiUsage = null;
            $enginesRan = [];

            if ($mode === 'local' || $mode === 'hybrid')
            {
                $localText = $this->ocrService->extractTextFromLocalFile($localPath);
                $enginesRan[] = 'local';
            }

            if ($mode === 'ai' || $mode === 'hybrid')
            {
                $aiResult = $this->aiOcrService->extractWithUsage($localPath, $teamId);
                $aiText = $aiResult['text'];
                $aiUsage = $aiResult['usage'];
                $enginesRan[] = 'ai';
            }

            if ($mode === 'ai' && $aiText === null)
            {
                $localText = $this->ocrService->extractTextFromLocalFile($localPath);
                $enginesRan[] = 'local_fallback';
            }

            $chosenText = null;
            $engineUsed = null;
            if ($mode === 'local')
            {
                $chosenText = $localText;
                $engineUsed = $localText !== null ? 'local' : null;
            } elseif ($mode === 'ai')
            {
                $chosenText = $aiText ?? $localText;
                $engineUsed = $aiText !== null ? 'ai' : ($localText !== null ? 'local_fallback' : null);
            } else
            {
                $localLen = mb_strlen((string) ($localText ?? ''));
                $aiLen = mb_strlen((string) ($aiText ?? ''));
                if ($aiLen > $localLen)
                {
                    $chosenText = $aiText;
                    $engineUsed = $aiText !== null ? 'ai' : null;
                } else
                {
                    $chosenText = $localText ?? $aiText;
                    $engineUsed = $localText !== null ? 'local' : ($aiText !== null ? 'ai' : null);
                }
            }

            return [
                'text' => $chosenText,
                'mode' => $mode,
                'engine_used' => $engineUsed,
                'engines_ran' => $enginesRan,
                'usage' => $engineUsed === 'ai' ? $aiUsage : null,
            ];
        } finally
        {
            if ($downloadedPath !== null && is_file($downloadedPath))
            {
                @unlink($downloadedPath);
            }
        }
    }

    private function resolveOcrMode(?int $teamId): string
    {
        if ($teamId === null)
        {
            return 'ai';
        }

        $mode = Str::lower((string) Team::withoutGlobalScopes()->find($teamId)?->getSetting('documents_ocr_mode', 'ai'));
        if (! in_array($mode, ['local', 'ai', 'hybrid'], true))
        {
            return 'ai';
        }

        return $mode;
    }

    private function resolveLocalPathFromUrl(string $fileUrl): ?string
    {
        $path = (string) parse_url($fileUrl, PHP_URL_PATH);
        if ($path === '')
        {
            return null;
        }

        if (Str::startsWith($path, '/storage/'))
        {
            $relative = ltrim(Str::after($path, '/storage/'), '/');
            foreach ([
                public_path('storage/'.$relative),
                storage_path('app/public/'.$relative),
            ] as $candidate)
            {
                if (is_file($candidate))
                {
                    return $candidate;
                }
            }

            return null;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $urlHost = parse_url($fileUrl, PHP_URL_HOST);
        if ($appHost !== null && $urlHost !== null && strcasecmp((string) $appHost, (string) $urlHost) === 0)
        {
            $candidate = public_path(ltrim($path, '/'));
            if (is_file($candidate))
            {
                return $candidate;
            }
        }

        return null;
    }

    private function downloadRemoteFileForOcr(string $fileUrl): ?string
    {
        if (! preg_match('#^https?://#i', $fileUrl))
        {
            return null;
        }

        $path = (string) parse_url($fileUrl, PHP_URL_PATH);
        if (! Str::contains($path, '/inbound-media/'))
        {
            return null;
        }

        try
        {
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->withHeaders(['User-Agent' => 'IDONEO-OCR/1.0'])
                ->get($fileUrl);
            if (! $response->successful())
            {
                return null;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > 12_000_000)
            {
                return null;
            }

            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'], true))
            {
                $extension = 'jpg';
            }

            $temp = tempnam(sys_get_temp_dir(), 'ocr_');
            if ($temp === false)
            {
                return null;
            }

            $target = $temp.'.'.$extension;
            @unlink($temp);
            if (file_put_contents($target, $body) === false)
            {
                return null;
            }

            return $target;
        } catch (\Throwable $e)
        {
            Log::warning('OCR remote download failed', [
                'url' => $fileUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractStructuredDataFromText(?string $text): array
    {
        if ($text === null || trim($text) === '')
        {
            return [];
        }

        $emails = $this->extractEmails($text);
        $phones = $this->extractPhoneNumbers($text);
        $website = $this->extractWebsite($text);
        $name = $this->extractLikelyName($text, $emails, $website);
        $title = $this->extractLikelyTitle($text);
        $company = $this->extractLikelyCompany($text, $name, $title);
        if ($company === null && $website !== null)
        {
            $company = $this->extractCompanyFromWebsite($website);
        }

        $invoice = $this->extractInvoiceData($text, $company);
        $part = $this->extractSparePartData($text, is_array($invoice) ? $invoice : null);

        return [
            'phones' => $phones,
            'emails' => $emails,
            'website' => $website,
            'name' => $name,
            'title' => $title,
            'company' => $company,
            'invoice' => $invoice,
            'part' => $part,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractPhoneNumbers(string $text): array
    {
        preg_match_all('/(?:\+?\d[\d\s\-\(\)\.]{6,}\d)/u', $text, $matches);

        return collect($matches[0] ?? [])
            ->map(function (string $raw): ?string
            {
                $normalized = preg_replace('/[^\d+]/', '', trim($raw)) ?? '';
                if ($normalized === '')
                {
                    return null;
                }

                if (str_starts_with($normalized, '+'))
                {
                    $digits = '+'.preg_replace('/[^\d]/', '', substr($normalized, 1));
                } else
                {
                    $digits = preg_replace('/[^\d]/', '', $normalized) ?? '';
                }

                $len = strlen(ltrim($digits, '+'));
                if ($len < 8 || $len > 15)
                {
                    return null;
                }

                return $digits;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function extractEmails(string $text): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $text, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $email): string => Str::lower(trim($email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function extractWebsite(string $text): ?string
    {
        preg_match('/\b(?:https?:\/\/)?(?:www\.)?[A-Z0-9.-]+\.[A-Z]{2,}\b/iu', $text, $match);
        if (! isset($match[0]))
        {
            return null;
        }

        $candidate = trim(Str::lower($match[0]));
        if (str_contains($candidate, '@'))
        {
            return null;
        }

        if (! str_starts_with($candidate, 'http://') && ! str_starts_with($candidate, 'https://'))
        {
            $candidate = 'https://'.$candidate;
        }

        return $candidate;
    }

    private function extractLikelyName(string $text, array $emails, ?string $website): ?string
    {
        $lines = preg_split('/\R+/', $text) ?: [];
        foreach ($lines as $line)
        {
            $candidate = trim($line);
            if ($candidate === '' || mb_strlen($candidate) < 4 || mb_strlen($candidate) > 60)
            {
                continue;
            }

            $lower = Str::lower($candidate);
            if (str_contains($lower, '@') || str_contains($lower, 'http') || str_contains($lower, 'www.'))
            {
                continue;
            }
            if (preg_match('/\d/', $candidate))
            {
                continue;
            }
            if (in_array($lower, ['asesoramiento financiero', 'bienestar economico', 'bienestar económico'], true))
            {
                continue;
            }

            $words = preg_split('/\s+/', $candidate) ?: [];
            if (count($words) >= 2 && count($words) <= 4)
            {
                return Str::title($candidate);
            }
        }

        if ($emails !== [])
        {
            return Str::title(Str::before((string) $emails[0], '@'));
        }

        if ($website !== null)
        {
            $host = parse_url($website, PHP_URL_HOST) ?: '';

            return $host !== '' ? Str::title(Str::before($host, '.')) : null;
        }

        return null;
    }

    private function extractLikelyTitle(string $text): ?string
    {
        $titles = [
            'ceo', 'cto', 'cfo', 'coo', 'founder', 'co-founder', 'director', 'manager',
            'asesor', 'consultor', 'abogado', 'arquitecto', 'ingeniero', 'diseñador', 'disenador',
        ];
        $lines = preg_split('/\R+/', $text) ?: [];
        foreach ($lines as $line)
        {
            $candidate = trim($line);
            if ($candidate === '' || mb_strlen($candidate) > 80)
            {
                continue;
            }
            $lower = Str::lower($candidate);
            foreach ($titles as $title)
            {
                if (str_contains($lower, $title))
                {
                    return Str::title($candidate);
                }
            }
        }

        return null;
    }

    private function extractLikelyCompany(string $text, ?string $name, ?string $title): ?string
    {
        $lines = preg_split('/\R+/', $text) ?: [];
        foreach ($lines as $line)
        {
            $candidate = trim($line);
            if ($candidate === '' || mb_strlen($candidate) < 3 || mb_strlen($candidate) > 80)
            {
                continue;
            }
            $lower = Str::lower($candidate);
            if (str_contains($lower, '@') || str_contains($lower, 'http') || str_contains($lower, 'www.'))
            {
                continue;
            }
            if (preg_match('/\d/', $candidate))
            {
                continue;
            }
            if ($name !== null && strcasecmp($candidate, $name) === 0)
            {
                continue;
            }
            if ($title !== null && strcasecmp($candidate, $title) === 0)
            {
                continue;
            }

            if (preg_match('/\b(s\.?l\.?|s\.?a\.?|llc|inc|ltda|gmbh|studio|group)\b/iu', $candidate))
            {
                return $candidate;
            }
        }

        return null;
    }

    private function extractCompanyFromWebsite(string $website): ?string
    {
        $host = (string) (parse_url($website, PHP_URL_HOST) ?: '');
        if ($host === '')
        {
            return null;
        }

        $host = Str::lower($host);
        if (str_starts_with($host, 'www.'))
        {
            $host = substr($host, 4);
        }

        $segments = explode('.', $host);
        if (count($segments) < 2)
        {
            return Str::title($host);
        }

        $base = $segments[count($segments) - 2];

        return $base !== '' ? Str::title(str_replace(['-', '_'], ' ', $base)) : null;
    }

    /**
     * @param  array<string, mixed>|null  $invoice
     * @return array{description?: string, code?: string, brand?: string, oem?: string}|null
     */
    private function extractSparePartData(string $text, ?array $invoice): ?array
    {
        if ($this->invoiceLooksStrongerThanPart($invoice))
        {
            return null;
        }

        $normalized = Str::lower(Str::ascii($text));
        $description = $this->extractSparePartDescription($text, $normalized);
        $code = $this->extractSparePartCode($text);
        $brand = $this->extractSparePartBrand($text);
        $oem = $this->extractSparePartOem($text, $code);

        if ($description === null && $code === null)
        {
            return null;
        }

        return array_filter([
            'description' => $description,
            'code' => $code,
            'brand' => $brand,
            'oem' => $oem,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>|null  $invoice
     */
    private function invoiceLooksStrongerThanPart(?array $invoice): bool
    {
        if (! is_array($invoice) || $invoice === [])
        {
            return false;
        }

        $hasInvoiceNumber = filled($invoice['document_number'] ?? null);
        $hasTotal = (float) ($invoice['total_amount'] ?? 0) > 0;
        $hasKeywords = ! empty($invoice['has_invoice_keywords']);

        return ($hasInvoiceNumber && $hasTotal) || ($hasKeywords && $hasInvoiceNumber);
    }

    private function extractSparePartDescription(string $text, string $normalized): ?string
    {
        $keywords = [
            'filtro', 'pastilla', 'bujia', 'disco', 'correa', 'bomba', 'radiador',
            'embrague', 'amortiguador', 'rodamiento', 'junta', 'sensor', 'inyector',
            'alternador', 'arranque', 'habitaculo', 'combustible', 'repuesto', 'pieza',
            'kit de', 'aceite', 'lubricante', 'helix', '10w', '5w', '15w', '20w',
        ];
        $lines = preg_split('/\R+/', $text) ?: [];
        foreach ($lines as $line)
        {
            $candidate = trim($line);
            if ($candidate === '' || mb_strlen($candidate) > 80)
            {
                continue;
            }

            $lineNormalized = Str::lower(Str::ascii($candidate));
            foreach ($keywords as $keyword)
            {
                if (str_contains($lineNormalized, $keyword))
                {
                    return Str::title($candidate);
                }
            }
        }

        foreach ($keywords as $keyword)
        {
            if (str_contains($normalized, $keyword))
            {
                return Str::title($keyword);
            }
        }

        return null;
    }

    private function extractSparePartCode(string $text): ?string
    {
        if (preg_match('/\b(?:ref(?:erencia)?|p\/n|pn|oem|n[ºo°]|c[oó]digo)\s*[:.]?\s*([A-Z0-9][A-Z0-9.\/\-]{3,24})/iu', $text, $match))
        {
            return strtoupper(trim($match[1]));
        }

        if (preg_match('/\b(\d{3}[A-Z]\d{6}[A-Z]?)\b/u', $text, $match))
        {
            return strtoupper(trim($match[1]));
        }

        if (preg_match('/\b([A-Z]{1,4}\s?\d{2,5}(?:[\/.]\d{1,4})?[A-Z]?)\b/iu', $text, $match))
        {
            return strtoupper(trim($match[1]));
        }

        if (preg_match('/\b(HX\s?\d)\b/iu', $text, $line) === 1 && preg_match('/\b(\d{1,2}W-?\d{2})\b/iu', $text, $visc) === 1)
        {
            return strtoupper(trim($line[1]).' '.$visc[1]);
        }

        if (preg_match('/\b(\d{1,2}W-?\d{2})\b/iu', $text, $match))
        {
            return strtoupper(trim($match[1]));
        }

        return null;
    }

    private function extractSparePartBrand(string $text): ?string
    {
        $brands = [
            'MANN', 'BOSCH', 'VALEO', 'SKF', 'SACHS', 'LUK', 'TRW', 'ATE', 'NGK',
            'DENSO', 'GATES', 'DAYCO', 'MAHLE', 'FEBI', 'SWAG', 'KYB', 'MONROE',
            'PURFLUX', 'FRAM', 'FILTRON', 'UFI', 'SOFIMA', 'MAGNETI', 'FIAT',
            'SHELL', 'CASTROL', 'MOBIL', 'ELF', 'TOTAL', 'YPF', 'PETRONAS',
            'REPSOL', 'VALVOLINE',
        ];
        $upper = mb_strtoupper($text);
        foreach ($brands as $brand)
        {
            if (preg_match('/\b'.preg_quote($brand, '/').'\b/u', $upper))
            {
                return Str::title($brand);
            }
        }

        return null;
    }

    private function extractSparePartOem(string $text, ?string $code): ?string
    {
        if (preg_match('/\b(?:oem|orig(?:inal)?)\s*[:.]?\s*([A-Z0-9][A-Z0-9.\/\-]{4,24})/iu', $text, $match))
        {
            $oem = strtoupper(trim($match[1]));
            if ($code === null || strcasecmp($oem, $code) !== 0)
            {
                return $oem;
            }
        }

        return null;
    }

    /**
     * @param  array{document_type:string,classification_status:string,classification_confidence:float,reason:string}  $classification
     * @param  array<string,mixed>  $extractedData
     * @return array{document_type:string,classification_status:string,classification_confidence:float,reason:string}
     */
    private function refineClassificationWithExtractedData(array $classification, array $extractedData, string $mimeType, string $fileName): array
    {
        if (($classification['document_type'] ?? '') !== 'unknown')
        {
            return $classification;
        }

        $invoiceData = is_array($extractedData['invoice'] ?? null) ? $extractedData['invoice'] : [];
        $hasInvoiceSignals = (float) ($invoiceData['total_amount'] ?? 0) > 0
            || filled($invoiceData['document_number'] ?? null)
            || (
                ! empty($invoiceData['has_invoice_keywords'])
                && filled($invoiceData['supplier_name'] ?? null)
                && filled($invoiceData['date'] ?? null)
            );
        if ($hasInvoiceSignals)
        {
            return [
                'document_type' => 'invoice',
                'classification_status' => 'classified',
                'classification_confidence' => 0.84,
                'reason' => 'OCR extracted invoice-like fields.',
            ];
        }

        $part = is_array($extractedData['part'] ?? null) ? $extractedData['part'] : [];
        if (filled($part['code'] ?? null) || filled($part['description'] ?? null))
        {
            return $classification;
        }

        $hasEmail = ! empty($extractedData['emails']);
        $hasPhone = ! empty($extractedData['phones']);
        $hasName = ! empty($extractedData['name']);
        $isLikelyContact = $hasEmail || $hasPhone || $hasName;
        if (! $isLikelyContact)
        {
            return $classification;
        }

        $isImage = Str::startsWith(Str::lower($mimeType), 'image/')
            || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $fileName);
        if (! $isImage)
        {
            return $classification;
        }

        return [
            'document_type' => 'business_card',
            'classification_status' => 'classified',
            'classification_confidence' => 0.88,
            'reason' => 'OCR extracted contact-like fields from image.',
        ];
    }

    /**
     * @param  array<string,mixed>  $extractedData
     */
    private function attachCreatedInvoiceIfApplicable(
        DocumentIngestion $ingestion,
        array $extractedData,
        ?int $teamId,
        ?string $sourceName,
    ): void {
        if (($ingestion->document_type ?? '') !== 'invoice' || $teamId === null || $teamId < 1)
        {
            return;
        }

        if ($sourceName === null || Str::lower(trim($sourceName)) !== 'whatsapp')
        {
            return;
        }

        if ($ingestion->entity_type === Invoice::class && $ingestion->entity_id !== null)
        {
            return;
        }

        if ($ingestion->entity_type !== null && $ingestion->entity_type !== Invoice::class)
        {
            return;
        }

        $invoiceData = is_array($extractedData['invoice'] ?? null) ? $extractedData['invoice'] : [];
        $totalAmount = round((float) ($invoiceData['total_amount'] ?? 0), 2);
        if ($totalAmount <= 0)
        {
            return;
        }

        $enterprise = $this->resolveOrCreateSupplierEnterprise(
            $teamId,
            (string) ($invoiceData['supplier_name'] ?? ''),
            (string) ($extractedData['company'] ?? ''),
        );
        if (! $enterprise instanceof Enterprise)
        {
            return;
        }

        $invoiceTypeId = $this->resolveInvoiceTypeId();
        if ($invoiceTypeId === null)
        {
            return;
        }

        $invoiceDate = $this->normalizeDateString((string) ($invoiceData['date'] ?? null)) ?? now()->toDateString();
        $dueDate = $this->normalizeDateString((string) ($invoiceData['due_date'] ?? null)) ?? $invoiceDate;
        $documentNumber = trim((string) ($invoiceData['document_number'] ?? ''));
        if ($documentNumber === '')
        {
            $documentNumber = 'WA-'.now()->format('YmdHis').'-'.$ingestion->id;
        }

        $currencyId = $this->resolveCurrencyIdByCode((string) ($invoiceData['currency_code'] ?? null));
        $lineConcept = trim((string) ($invoiceData['line_concept'] ?? ''));
        if ($lineConcept === '')
        {
            $lineConcept = 'Factura recibida por WhatsApp';
        }

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $enterprise->id,
            'billing_id' => null,
            'type_id' => $invoiceTypeId,
            'operation' => 'buy',
            'number' => Str::limit($documentNumber, 255, ''),
            'date' => $invoiceDate,
            'due_date' => $dueDate,
            'gross_amount' => $totalAmount,
            'discount' => 0,
            'total_amount' => $totalAmount,
            'balance' => $totalAmount,
            'currency_id' => $currencyId,
            'status' => 1,
            'source_provider' => 'manual',
            'source_reference_id' => 'document_ingestion:'.$ingestion->id,
            'source_synced_at' => now(),
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => null,
            'description' => $lineConcept,
            'quantity' => 1,
            'unit_price' => $totalAmount,
            'discount' => 0,
            'tax_percentage' => 0,
        ]);

        $ingestion->entity_type = Invoice::class;
        $ingestion->entity_id = $invoice->id;
        $ingestion->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function extractInvoiceData(string $text, ?string $fallbackCompany): array
    {
        $invoiceDate = $this->extractDateByLabels($text, [
            'fecha factura',
            'fecha emision',
            'fecha emisión',
            'invoice date',
            'fecha',
        ]);
        $dueDate = $this->extractDateByLabels($text, [
            'fecha vencimiento',
            'vencimiento',
            'due date',
            'payment due',
        ]);

        return [
            'supplier_name' => $this->extractSupplierName($text, $fallbackCompany),
            'document_number' => $this->extractInvoiceNumber($text),
            'date' => $invoiceDate,
            'due_date' => $dueDate ?? $invoiceDate,
            'currency_code' => $this->extractCurrencyCode($text),
            'total_amount' => $this->extractInvoiceTotal($text),
            'line_concept' => $this->extractLikelyInvoiceConcept($text),
            'has_invoice_keywords' => $this->containsInvoiceKeywords($text),
        ];
    }

    private function containsInvoiceKeywords(string $text): bool
    {
        $normalized = Str::lower($text);

        return str_contains($normalized, 'factura')
            || str_contains($normalized, 'invoice')
            || str_contains($normalized, 'iva')
            || str_contains($normalized, 'total a pagar')
            || str_contains($normalized, 'importe total');
    }

    private function extractInvoiceNumber(string $text): ?string
    {
        if (preg_match('/(?:factura|invoice|n[úu]mero(?:\s+de)?(?:\s+factura)?)\s*[:#\-]?\s*([A-Z0-9\-\/\.]{3,})/iu', $text, $matches) === 1)
        {
            return Str::limit(trim((string) ($matches[1] ?? '')), 120, '');
        }

        return null;
    }

    private function extractSupplierName(string $text, ?string $fallbackCompany): ?string
    {
        if ($fallbackCompany !== null && trim($fallbackCompany) !== '')
        {
            return Str::limit(trim($fallbackCompany), 255, '');
        }

        $lines = preg_split('/\R+/', $text) ?: [];
        foreach ($lines as $line)
        {
            $candidate = trim($line);
            if ($candidate === '' || mb_strlen($candidate) < 3 || mb_strlen($candidate) > 90)
            {
                continue;
            }

            $lower = Str::lower($candidate);
            if (
                str_contains($lower, 'factura')
                || str_contains($lower, 'invoice')
                || str_contains($lower, 'fecha')
                || str_contains($lower, 'total')
                || str_contains($lower, '@')
                || str_contains($lower, 'www.')
            ) {
                continue;
            }

            if (preg_match('/\d/', $candidate) === 1)
            {
                continue;
            }

            return Str::limit($candidate, 255, '');
        }

        return null;
    }

    private function extractCurrencyCode(string $text): ?string
    {
        if (preg_match('/\b(EUR|USD|ARS|MXN|GBP|COP|CLP|PEN|UYU|BRL)\b/i', $text, $matches) === 1)
        {
            return strtoupper((string) $matches[1]);
        }

        if (str_contains($text, '€'))
        {
            return 'EUR';
        }

        if (str_contains($text, '$'))
        {
            return 'USD';
        }

        return null;
    }

    private function extractInvoiceTotal(string $text): ?float
    {
        $patterns = [
            '/(?:total(?:\s+a\s+pagar)?|importe\s+total|amount\s+due)\s*[:\-]?\s*([$€]?\s*[0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]{2})?)/iu',
            '/(?:total)\s*[:\-]?\s*([$€]?\s*[0-9]+(?:[.,][0-9]{2})?)/iu',
        ];

        foreach ($patterns as $pattern)
        {
            if (preg_match($pattern, $text, $matches) === 1)
            {
                $parsedAmount = $this->parseDecimalAmount((string) ($matches[1] ?? '0'));
                if ($parsedAmount > 0)
                {
                    return round($parsedAmount, 2);
                }
            }
        }

        return null;
    }

    private function extractLikelyInvoiceConcept(string $text): ?string
    {
        $lines = preg_split('/\R+/', $text) ?: [];
        foreach ($lines as $line)
        {
            $candidate = trim($line);
            if ($candidate === '' || mb_strlen($candidate) < 8 || mb_strlen($candidate) > 140)
            {
                continue;
            }

            $lower = Str::lower($candidate);
            if (
                str_contains($lower, 'factura')
                || str_contains($lower, 'invoice')
                || str_contains($lower, 'fecha')
                || str_contains($lower, 'total')
                || str_contains($lower, '@')
            ) {
                continue;
            }

            return Str::limit($candidate, 255, '');
        }

        return null;
    }

    private function extractDateByLabels(string $text, array $labels): ?string
    {
        foreach ($labels as $label)
        {
            $pattern = '/'.preg_quote($label, '/').'\s*[:\-]?\s*([0-9]{1,4}[\/\.\-][0-9]{1,2}[\/\.\-][0-9]{1,4})/iu';
            if (preg_match($pattern, $text, $matches) === 1)
            {
                return $this->normalizeDateString((string) ($matches[1] ?? null));
            }
        }

        return null;
    }

    private function normalizeDateString(?string $value): ?string
    {
        $dateValue = trim((string) $value);
        if ($dateValue === '')
        {
            return null;
        }

        $normalized = str_replace(['.', '/'], '-', $dateValue);
        $formats = ['Y-m-d', 'd-m-Y', 'd-m-y', 'm-d-Y', 'm-d-y', 'Y-d-m'];

        foreach ($formats as $format)
        {
            try
            {
                $parsed = Carbon::createFromFormat($format, $normalized);
                if ($parsed !== false)
                {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable)
            {
            }
        }

        try
        {
            return Carbon::parse($normalized)->format('Y-m-d');
        } catch (\Throwable)
        {
            return null;
        }
    }

    private function parseDecimalAmount(string $value): float
    {
        $amount = trim($value);
        $amount = preg_replace('/[^0-9,\.\-]/', '', $amount) ?? '';
        if ($amount === '')
        {
            return 0;
        }

        if (str_contains($amount, ',') && str_contains($amount, '.'))
        {
            if (strrpos($amount, ',') > strrpos($amount, '.'))
            {
                $amount = str_replace('.', '', $amount);
                $amount = str_replace(',', '.', $amount);
            } else
            {
                $amount = str_replace(',', '', $amount);
            }
        } elseif (str_contains($amount, ','))
        {
            $amount = str_replace(',', '.', $amount);
        }

        return (float) $amount;
    }

    private function resolveCurrencyIdByCode(?string $code): ?int
    {
        $normalizedCode = strtoupper(trim((string) $code));
        if ($normalizedCode === '')
        {
            return null;
        }

        $currencyId = Currency::query()
            ->whereRaw('UPPER(code) = ?', [$normalizedCode])
            ->value('id');

        return $currencyId !== null ? (int) $currencyId : null;
    }

    private function resolveInvoiceTypeId(): ?int
    {
        $id = InvoiceType::query()->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function resolveOrCreateSupplierEnterprise(int $teamId, string $supplierName, string $fallbackCompany): ?Enterprise
    {
        $candidateName = trim($supplierName) !== '' ? trim($supplierName) : trim($fallbackCompany);
        if ($candidateName === '')
        {
            return null;
        }

        $normalizedTarget = $this->normalizeText($candidateName);

        $existing = Enterprise::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->get(['id', 'name', 'type_id'])
            ->sortByDesc(function (Enterprise $enterprise) use ($normalizedTarget): float
            {
                $normalizedName = $this->normalizeText($enterprise->name);
                if ($normalizedName === $normalizedTarget)
                {
                    return 100.0;
                }

                if (
                    str_contains($normalizedName, $normalizedTarget)
                    || str_contains($normalizedTarget, $normalizedName)
                ) {
                    return 85.0;
                }

                similar_text($normalizedName, $normalizedTarget, $score);

                return (float) $score;
            })
            ->first();

        if ($existing instanceof Enterprise)
        {
            $normalizedExisting = $this->normalizeText($existing->name);
            if (
                $normalizedExisting === $normalizedTarget
                || str_contains($normalizedExisting, $normalizedTarget)
                || str_contains($normalizedTarget, $normalizedExisting)
            ) {
                return $existing;
            }

            similar_text($normalizedExisting, $normalizedTarget, $score);
            if ((float) $score >= 60.0)
            {
                return $existing;
            }
        }

        $supplierTypeId = EnterpriseType::query()
            ->whereRaw('LOWER(name) in (?, ?)', ['supplier', 'proveedor'])
            ->value('id');
        if ($supplierTypeId === null)
        {
            $supplierTypeId = 2;
        }

        return Enterprise::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'type_id' => (int) $supplierTypeId,
            'status_id' => 1,
            'name' => Str::limit($candidateName, 255, ''),
        ]);
    }

    private function normalizeText(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim();
    }

    /**
     * @param  array<string,mixed>  $extractedData
     */
    private function attachCreatedContactIfApplicable(
        DocumentIngestion $ingestion,
        array $extractedData,
        ?int $teamId,
        ?int $sourceId,
    ): void {
        if (($ingestion->document_type ?? '') !== 'business_card' || $teamId === null || $teamId < 1)
        {
            return;
        }

        if ($ingestion->entity_type === Contact::class && $ingestion->entity_id !== null)
        {
            return;
        }

        $email = (string) (($extractedData['emails'][0] ?? '') ?: '');
        $phone = (string) (($extractedData['phones'][0] ?? '') ?: '');
        $name = trim((string) ($extractedData['name'] ?? ''));
        if ($email === '' && $phone === '' && $name === '')
        {
            return;
        }

        $normalizedPhone = preg_replace('/[^0-9]/', '', $phone) ?? '';
        $creatorId = Team::withoutGlobalScopes()->find($teamId)?->user_id;
        if ($creatorId === null)
        {
            return;
        }

        $existingQuery = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId);

        if ($email !== '' && $normalizedPhone !== '')
        {
            $existingQuery->where(function ($query) use ($email, $normalizedPhone)
            {
                $query->where('email', $email)
                    ->orWhere('phone', $normalizedPhone);
            });
        } elseif ($email !== '')
        {
            $existingQuery->where('email', $email);
        } else
        {
            $existingQuery->where('phone', $normalizedPhone);
        }

        $existing = $existingQuery->first();

        if ($existing === null)
        {
            $profileParts = ['Creado automáticamente desde OCR de tarjeta.'];
            if (! empty($extractedData['title']))
            {
                $profileParts[] = 'Cargo: '.(string) $extractedData['title'];
            }
            if (! empty($extractedData['company']))
            {
                $profileParts[] = 'Empresa: '.(string) $extractedData['company'];
            }
            if (! empty($extractedData['website']))
            {
                $profileParts[] = 'Web: '.(string) $extractedData['website'];
            }

            $existing = Contact::withoutGlobalScopes()->create([
                'team_id' => $teamId,
                'user_id' => null,
                'name' => $name !== '' ? Str::limit($name, 255, '') : 'Contacto OCR',
                'surname' => null,
                'email' => $email !== '' ? Str::limit($email, 255, '') : null,
                'phone' => $normalizedPhone !== '' ? $normalizedPhone : null,
                'source_id' => $sourceId,
                'profile' => implode("\n", $profileParts),
                'country' => 724,
                'language' => 'es',
                'creator_id' => (int) $creatorId,
                'responsible_id' => (int) $creatorId,
                'status_id' => 1,
                'data' => [
                    'ocr_title' => $extractedData['title'] ?? null,
                    'ocr_company' => $extractedData['company'] ?? null,
                    'ocr_website' => $extractedData['website'] ?? null,
                ],
            ]);
        }

        $ingestion->entity_type = Contact::class;
        $ingestion->entity_id = $existing->id;
        $ingestion->save();
    }
}
