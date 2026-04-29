<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\DocumentIngestion;
use App\Models\Source;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DocumentIngestionService
{
    public function __construct(private readonly DocumentOcrService $ocrService) {}

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
                $ocrText = $this->extractOcrText($fileUrl, $mimeType);
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
                    'classification_meta' => [
                        'reason' => $classification['reason'],
                        'channel' => Str::lower($sourceName),
                        'ocr_applied' => $ocrText !== null,
                    ],
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
            $ocrText = $this->extractOcrText($fileUrl, $mimeType);
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
                'classification_meta' => array_merge((array) ($documentIngestion->classification_meta ?? []), [
                    'reason' => $classification['reason'],
                    'ocr_applied' => $ocrText !== null,
                    'reprocessed_at' => now()->toIso8601String(),
                ]),
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

    private function extractOcrText(string $fileUrl, string $mimeType): ?string
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
            return null;
        }

        $localPath = $this->resolveLocalPathFromUrl($fileUrl);
        if ($localPath === null)
        {
            return null;
        }

        return $this->ocrService->extractTextFromLocalFile($localPath);
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
            $candidate = public_path('storage/'.$relative);

            return is_file($candidate) ? $candidate : null;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $urlHost = parse_url($fileUrl, PHP_URL_HOST);
        if ($appHost !== null && $urlHost !== null && strcasecmp((string) $appHost, (string) $urlHost) === 0)
        {
            $candidate = public_path(ltrim($path, '/'));

            return is_file($candidate) ? $candidate : null;
        }

        return null;
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

        return [
            'phones' => $phones,
            'emails' => $emails,
            'website' => $website,
            'name' => $name,
            'title' => $title,
            'company' => $company,
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
