<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Team;
use App\Support\AiTasks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use function Laravel\Ai\agent;

class ExpenseDocumentDetectionService
{
    private const AI_EXTRACTION_INSTRUCTIONS = <<<'PROMPT'
You extract expense invoice data from OCR text.
Return ONLY valid JSON with this structure:
{
  "enterprise_name": "string|null",
  "document_number": "string|null",
  "invoice_date": "YYYY-MM-DD|null",
  "due_date": "YYYY-MM-DD|null",
  "payment_date": "YYYY-MM-DD|null",
  "currency_code": "ISO code like EUR/USD/ARS|null",
  "total_amount": number|null,
  "lines": [
    {
      "concept": "string",
      "base_amount": number,
      "vat_percent": number,
      "retention_percent": number,
      "allocation_percent": number
    }
  ]
}

Rules:
- Use null when unknown.
- Keep numbers as decimal numbers.
- If a tax/retention is unknown, use 0.
- allocation_percent must be 100 when unknown.
- enterprise_name MUST be the issuer/supplier (not customer/recipient/buyer).
- document_number MUST NOT be a phone number.
- lines must be product/service rows only (taxable base before VAT).
- Do NOT include tax breakdown rows (IVA, base imponible, subtotal, total) as separate lines.
- Do not include markdown or explanations.
PROMPT;

    public function __construct(
        private readonly DocumentOcrService $ocrService,
        private readonly DocumentAiOcrService $aiOcrService,
        private readonly ExpenseSupplierService $supplierService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function detectFromUploadedFile(UploadedFile $file, int $teamId): array
    {
        $absolutePath = $file->getRealPath();

        if (! is_string($absolutePath) || $absolutePath === '' || ! is_file($absolutePath))
        {
            throw new \RuntimeException('No se pudo procesar el archivo subido.');
        }

        $ocrResult = $this->extractOcrText($absolutePath, $teamId);
        $heuristicData = $this->extractStructuredDataWithHeuristics($ocrResult['text'], $teamId);
        $aiData = $this->extractStructuredDataWithAi($ocrResult['text'], $teamId, $ocrResult['mode']);
        $detectedData = $this->mergeDetectedData($heuristicData, $aiData);

        $supplierResolution = $this->supplierService->resolveForDetectedInvoice(
            $file,
            $ocrResult['text'],
            $detectedData,
            $teamId,
            $ocrResult['mode'],
        );

        $currencyId = $this->resolveCurrencyId($detectedData['currency_code'] ?? null);
        $lines = $this->normalizeLines($detectedData['lines'] ?? [], $detectedData['total_amount'] ?? null);
        $totalAmount = $this->resolveTotalAmount($detectedData['total_amount'] ?? null, $lines);

        return [
            'enterprise_id' => $supplierResolution['enterprise_id'],
            'enterprise_name' => $supplierResolution['enterprise_name'],
            'enterprise_match' => $supplierResolution['match'],
            'detected_supplier' => $this->formatDetectedSupplierForResponse($supplierResolution['supplier']),
            'document_number' => $detectedData['document_number'] ?? null,
            'date' => $this->normalizeDate($detectedData['invoice_date'] ?? null),
            'due_date' => $this->normalizeDate($detectedData['due_date'] ?? null)
                ?? $this->normalizeDate($detectedData['invoice_date'] ?? null),
            'payment_date' => $this->normalizeDate($detectedData['payment_date'] ?? null)
                ?? $this->normalizeDate($detectedData['invoice_date'] ?? null),
            'currency_id' => $currencyId,
            'currency_code' => $detectedData['currency_code'] ?? null,
            'payment_amount' => $totalAmount,
            'lines' => $lines,
            'ocr' => [
                'mode' => $ocrResult['mode'],
                'engine_used' => $ocrResult['engine_used'],
                'engines_ran' => $ocrResult['engines_ran'],
                'text_length' => mb_strlen((string) ($ocrResult['text'] ?? '')),
            ],
        ];
    }

    private function resolveOcrMode(int $teamId): string
    {
        $mode = Str::lower((string) Team::withoutGlobalScopes()->find($teamId)?->getSetting('documents_ocr_mode', 'ai'));

        if (! in_array($mode, ['local', 'ai', 'hybrid'], true))
        {
            return 'ai';
        }

        return $mode;
    }

    /**
     * @return array{text:?string,mode:string,engine_used:?string,engines_ran:array<int,string>}
     */
    private function extractOcrText(string $absolutePath, int $teamId): array
    {
        $mode = $this->resolveOcrMode($teamId);
        $localText = null;
        $aiText = null;
        $enginesRan = [];

        if ($mode === 'local' || $mode === 'hybrid')
        {
            $localText = $this->ocrService->extractTextFromLocalFile($absolutePath);
            $enginesRan[] = 'local';
        }

        if ($mode === 'ai' || $mode === 'hybrid')
        {
            $aiText = $this->aiOcrService->extractTextFromLocalFile($absolutePath, $teamId);
            $enginesRan[] = 'ai';
        }

        if ($mode === 'ai' && $aiText === null)
        {
            $localText = $this->ocrService->extractTextFromLocalFile($absolutePath);
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
            $localLength = mb_strlen((string) ($localText ?? ''));
            $aiLength = mb_strlen((string) ($aiText ?? ''));

            if ($aiLength > $localLength)
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractStructuredDataWithHeuristics(?string $ocrText, int $teamId): array
    {
        $text = trim((string) $ocrText);

        if ($text === '')
        {
            return [
                'enterprise_name' => null,
                'document_number' => null,
                'invoice_date' => null,
                'due_date' => null,
                'payment_date' => null,
                'currency_code' => null,
                'total_amount' => null,
                'lines' => [],
            ];
        }

        $knownPhones = $this->extractPhoneCandidates($text);
        $enterpriseName = $this->extractEnterpriseName($text, $teamId);
        $documentNumber = $this->extractDocumentNumber($text, $knownPhones);
        $invoiceDate = $this->extractDateByLabel($text, ['fecha factura', 'fecha', 'invoice date', 'date']);
        $dueDate = $this->extractDateByLabel($text, ['fecha vencimiento', 'vencimiento', 'due date', 'payment due']);
        $currencyCode = $this->extractCurrencyCode($text);
        $vatPercent = $this->extractPercentageByLabel($text, ['iva', 'vat']);
        $retentionPercent = $this->extractPercentageByLabel($text, ['retención', 'retencion', 'withholding']);
        $totalAmount = $this->extractTotalAmount($text);

        $lines = $this->extractInvoiceLines($text, $vatPercent, $retentionPercent, $totalAmount);
        if ($lines === [] && $totalAmount !== null && $totalAmount > 0)
        {
            $estimatedBase = $totalAmount;
            if ($vatPercent > 0)
            {
                $estimatedBase = $estimatedBase / (1 + ($vatPercent / 100));
            }
            if ($retentionPercent > 0)
            {
                $estimatedBase = $estimatedBase / (1 - ($retentionPercent / 100));
            }

            $lines[] = [
                'concept' => 'Gasto detectado',
                'base_amount' => round(max($estimatedBase, 0.01), 2),
                'vat_percent' => $vatPercent,
                'retention_percent' => $retentionPercent,
                'allocation_percent' => 100,
            ];
        }

        return [
            'enterprise_name' => $enterpriseName,
            'document_number' => $documentNumber,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate ?? $invoiceDate,
            'payment_date' => $invoiceDate,
            'currency_code' => $currencyCode,
            'total_amount' => $totalAmount,
            'lines' => $lines,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractStructuredDataWithAi(?string $ocrText, int $teamId, string $mode): ?array
    {
        if (! in_array($mode, ['ai', 'hybrid'], true))
        {
            return null;
        }

        $text = trim((string) $ocrText);

        if ($text === '')
        {
            return null;
        }

        try
        {
            $response = agent(
                instructions: self::AI_EXTRACTION_INSTRUCTIONS,
                messages: [],
                tools: [],
            )->prompt($text, [], provider: AiTasks::provider('ocr'));

            TokenUsageLogService::logFromAiResponse(
                teamId: $teamId,
                service: 'ExpenseDocumentDetectionService',
                usage: $response->usage ?? null,
                moduleKey: 'invoices',
                inputSize: strlen($text),
            );

            $raw = trim((string) ($response->text ?? ''));
            if ($raw === '')
            {
                return null;
            }

            $raw = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $raw) ?? $raw;
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $exception)
        {
            Log::warning('Expense document AI extraction failed', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $heuristicData
     * @param  array<string, mixed>|null  $aiData
     * @return array<string, mixed>
     */
    private function mergeDetectedData(array $heuristicData, ?array $aiData): array
    {
        if (! is_array($aiData))
        {
            return $heuristicData;
        }

        $merged = $heuristicData;
        $fields = ['enterprise_name', 'document_number', 'invoice_date', 'due_date', 'payment_date', 'currency_code', 'total_amount'];

        foreach ($fields as $field)
        {
            if (array_key_exists($field, $aiData) && filled($aiData[$field]))
            {
                $merged[$field] = $aiData[$field];
            }
        }

        $heuristicLines = is_array($heuristicData['lines'] ?? null) ? $heuristicData['lines'] : [];
        $aiLines = is_array($aiData['lines'] ?? null) ? $aiData['lines'] : [];

        if ($aiLines !== [] && $heuristicLines === [])
        {
            $merged['lines'] = $aiLines;
        } elseif ($aiLines !== [] && count($aiLines) >= count($heuristicLines))
        {
            $merged['lines'] = $aiLines;
        }

        return $merged;
    }

    private function extractEnterpriseName(string $text, int $teamId): ?string
    {
        $ownCompanyNames = $this->resolveOwnCompanyNames($teamId);
        $labeledPatterns = [
            '/(?:empresa|proveedor|raz[oó]n social|supplier|vendor)\s*[:\-]\s*([^\r\n]+)/iu',
            '/(?:emisor|issuer|from)\s*[:\-]\s*([^\r\n]+)/iu',
        ];

        foreach ($labeledPatterns as $pattern)
        {
            if (preg_match($pattern, $text, $matches) === 1)
            {
                $candidate = trim((string) ($matches[1] ?? ''));
                if ($candidate !== '' && ! $this->isOwnCompanyCandidate($candidate, $ownCompanyNames))
                {
                    return Str::limit($candidate, 255, '');
                }
            }
        }

        $lines = preg_split('/\R+/', $text) ?: [];
        $bestCandidate = null;
        $bestScore = 0;

        foreach ($lines as $index => $line)
        {
            $candidate = trim($line);
            if (mb_strlen($candidate) < 4 || mb_strlen($candidate) > 80)
            {
                continue;
            }

            if (preg_match('/\d/', $candidate) === 1)
            {
                continue;
            }

            $lowerCandidate = Str::lower($candidate);
            if (
                str_contains($lowerCandidate, 'factura')
                || str_contains($lowerCandidate, 'invoice')
                || str_contains($lowerCandidate, 'fecha')
                || str_contains($lowerCandidate, 'total')
                || str_contains($lowerCandidate, 'iva')
                || str_contains($lowerCandidate, 'cliente')
                || str_contains($lowerCandidate, 'customer')
                || str_contains($lowerCandidate, 'destinatario')
                || str_contains($lowerCandidate, 'comprador')
            ) {
                continue;
            }

            if ($this->isOwnCompanyCandidate($candidate, $ownCompanyNames))
            {
                continue;
            }

            $score = 0;
            if ((int) $index < 8)
            {
                $score += 20;
            }
            if (preg_match('/\b(s\.?l\.?|s\.?a\.?|llc|inc|ltda|gmbh|group|telecom|communications)\b/iu', $candidate) === 1)
            {
                $score += 25;
            }
            if (preg_match('/\b(proveedor|supplier|vendor|emisor|issuer)\b/iu', $candidate) === 1)
            {
                $score += 20;
            }

            if ($score > $bestScore)
            {
                $bestScore = $score;
                $bestCandidate = $candidate;
            }
        }

        if ($bestCandidate !== null && $bestScore >= 20)
        {
            return Str::limit($bestCandidate, 255, '');
        }

        return null;
    }

    private function extractDocumentNumber(string $text, array $knownPhones): ?string
    {
        $patterns = [
            '/(?:n[úu]m(?:ero)?\s+(?:de\s+)?factura|n[º°o]\s*factura|factura\s*(?:n[º°o]|#|num(?:ero)?)|invoice\s*(?:no|number|#)|ref(?:erencia)?\s+factura)\s*[:#\-]?\s*([A-Z0-9][A-Z0-9\-\/\.]{2,})/iu',
            '/(?:factura|invoice)\s*[:#\-]?\s*([A-Z]{1,4}[-\/]?[0-9][A-Z0-9\-\/\.]{2,})/iu',
        ];

        foreach ($patterns as $pattern)
        {
            if (preg_match($pattern, $text, $matches) === 1)
            {
                $candidate = trim((string) ($matches[1] ?? ''), " \t\n\r\0\x0B:;,.#");
                if ($candidate !== '' && ! $this->isLikelyPhoneNumber($candidate, $knownPhones))
                {
                    return Str::limit($candidate, 120, '');
                }
            }
        }

        return null;
    }

    private function extractDateByLabel(string $text, array $labels): ?string
    {
        foreach ($labels as $label)
        {
            $pattern = '/'.preg_quote($label, '/').'\s*[:\-]?\s*([0-9]{1,4}[\/\.\-][0-9]{1,2}[\/\.\-][0-9]{1,4})/iu';
            if (preg_match($pattern, $text, $matches) === 1)
            {
                return $this->normalizeDate((string) ($matches[1] ?? null));
            }
        }

        if (preg_match('/\b([0-9]{4}[\/\.\-][0-9]{1,2}[\/\.\-][0-9]{1,2})\b/u', $text, $matches) === 1)
        {
            return $this->normalizeDate((string) ($matches[1] ?? null));
        }

        if (preg_match('/\b([0-9]{1,2}[\/\.\-][0-9]{1,2}[\/\.\-][0-9]{2,4})\b/u', $text, $matches) === 1)
        {
            return $this->normalizeDate((string) ($matches[1] ?? null));
        }

        return null;
    }

    private function normalizeDate(?string $value): ?string
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

    private function extractPercentageByLabel(string $text, array $labels): float
    {
        foreach ($labels as $label)
        {
            $pattern = '/'.preg_quote($label, '/').'\s*[:\-]?\s*([0-9]{1,2}(?:[.,][0-9]{1,2})?)\s*%/iu';
            if (preg_match($pattern, $text, $matches) === 1)
            {
                return round($this->toFloat((string) ($matches[1] ?? '0')), 2);
            }
        }

        return 0.0;
    }

    private function extractTotalAmount(string $text): ?float
    {
        $patterns = [
            '/(?:total(?:\s+a\s+pagar)?|importe\s+total|amount\s+due)\s*[:\-]?\s*([$€]?\s*[0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]{2})?)/iu',
        ];

        foreach ($patterns as $pattern)
        {
            if (preg_match($pattern, $text, $matches) === 1)
            {
                $amount = $this->toFloat((string) ($matches[1] ?? '0'));
                if ($amount > 0)
                {
                    return round($amount, 2);
                }
            }
        }

        return null;
    }

    private function toFloat(mixed $value): float
    {
        $numeric = trim((string) $value);
        $numeric = preg_replace('/[^0-9,\.\-]/', '', $numeric) ?? '';

        if ($numeric === '')
        {
            return 0.0;
        }

        if (str_contains($numeric, ',') && str_contains($numeric, '.'))
        {
            if (strrpos($numeric, ',') > strrpos($numeric, '.'))
            {
                $numeric = str_replace('.', '', $numeric);
                $numeric = str_replace(',', '.', $numeric);
            } else
            {
                $numeric = str_replace(',', '', $numeric);
            }
        } elseif (str_contains($numeric, ','))
        {
            $numeric = str_replace(',', '.', $numeric);
        }

        return (float) $numeric;
    }

    /**
     * @param  array<string, string|null>  $supplier
     * @return array<string, string|null>
     */
    private function formatDetectedSupplierForResponse(array $supplier): array
    {
        $name = $supplier['legal_name'] ?? $supplier['brand_name'] ?? null;

        return [
            'name' => $name,
            'brand_name' => $supplier['brand_name'] ?? null,
            'legal_name' => $supplier['legal_name'] ?? null,
            'identification_number' => $supplier['identification_number'] ?? null,
            'email' => $supplier['email'] ?? null,
            'phone' => $supplier['phone'] ?? null,
            'website' => $supplier['website'] ?? null,
            'address' => $supplier['address'] ?? null,
            'postal_code' => $supplier['postal_code'] ?? null,
            'locality' => $supplier['locality'] ?? null,
            'province' => $supplier['province'] ?? null,
            'country' => $supplier['country'] ?? null,
        ];
    }

    private function resolveCurrencyId(?string $currencyCode): ?int
    {
        $code = strtoupper(trim((string) $currencyCode));
        if ($code === '')
        {
            return null;
        }

        $currencyId = Currency::query()
            ->active()
            ->whereRaw('UPPER(code) = ?', [$code])
            ->value('id');

        return $currencyId !== null ? (int) $currencyId : null;
    }

    /**
     * @param  array<int, mixed>  $lines
     * @return array<int, array<string, float|string>>
     */
    private function normalizeLines(array $lines, ?float $totalAmount): array
    {
        $normalizedLines = collect($lines)
            ->filter(fn ($line): bool => is_array($line))
            ->map(function (array $line, int $index): array
            {
                $baseAmount = round(max($this->toFloat($line['base_amount'] ?? 0), 0), 2);
                $vatPercent = round(max($this->toFloat($line['vat_percent'] ?? 0), 0), 2);
                $retentionPercent = round(max($this->toFloat($line['retention_percent'] ?? 0), 0), 2);
                $allocationPercent = round(max($this->toFloat($line['allocation_percent'] ?? 100), 0.01), 2);

                return [
                    'concept' => trim((string) ($line['concept'] ?? '')) !== ''
                        ? trim((string) $line['concept'])
                        : 'Concepto '.($index + 1),
                    'base_amount' => $baseAmount,
                    'vat_percent' => min($vatPercent, 100),
                    'retention_percent' => min($retentionPercent, 100),
                    'allocation_percent' => min($allocationPercent, 100),
                ];
            })
            ->filter(fn (array $line): bool => (float) $line['base_amount'] > 0)
            ->values()
            ->all();

        $normalizedLines = $this->filterTaxBreakdownLines($normalizedLines, $totalAmount);

        if ($normalizedLines === [] && $totalAmount !== null && $totalAmount > 0)
        {
            $normalizedLines[] = [
                'concept' => 'Gasto detectado',
                'base_amount' => round($totalAmount, 2),
                'vat_percent' => 0.0,
                'retention_percent' => 0.0,
                'allocation_percent' => 100.0,
            ];
        }

        return $normalizedLines;
    }

    /**
     * @param  array<int, array<string, float|string>>  $lines
     * @return array<int, array<string, float|string>>
     */
    private function filterTaxBreakdownLines(array $lines, ?float $totalAmount): array
    {
        if ($lines === [])
        {
            return [];
        }

        $lines = collect($lines)
            ->reject(fn (array $line): bool => $this->isTaxOrSummaryConcept((string) $line['concept']))
            ->values()
            ->all();

        if ($lines === [])
        {
            return [];
        }

        $lines = collect($lines)
            ->reject(function (array $line) use ($lines): bool
            {
                $baseAmount = (float) $line['base_amount'];

                foreach ($lines as $otherLine)
                {
                    if ($otherLine === $line)
                    {
                        continue;
                    }

                    $otherBase = (float) $otherLine['base_amount'];
                    $expectedVat = round($otherBase * ((float) $otherLine['vat_percent'] / 100), 2);
                    $expectedRetention = round($otherBase * ((float) $otherLine['retention_percent'] / 100), 2);

                    if ($this->amountsAreClose($baseAmount, $expectedVat) || $this->amountsAreClose($baseAmount, $expectedRetention))
                    {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();

        $lines = collect($lines)
            ->groupBy(fn (array $line): string => number_format((float) $line['base_amount'], 2, '.', ''))
            ->map(function ($group)
            {
                if ($group->count() === 1)
                {
                    return $group->first();
                }

                return $group
                    ->sortByDesc(fn (array $line): int => mb_strlen((string) $line['concept']))
                    ->first();
            })
            ->values()
            ->all();

        if ($totalAmount !== null && $totalAmount > 0 && count($lines) > 1)
        {
            $lines = collect($lines)
                ->reject(fn (array $line): bool => $this->amountsAreClose((float) $line['base_amount'], $totalAmount))
                ->values()
                ->all();
        }

        return array_values($lines);
    }

    private function isTaxOrSummaryConcept(string $concept): bool
    {
        $normalizedConcept = $this->normalizeText($concept);

        if ($normalizedConcept === '')
        {
            return true;
        }

        $exactMatches = [
            'base imponible',
            'subtotal',
            'sub total',
            'total',
            'importe total',
            'total factura',
            'total a pagar',
            'cuota iva',
            'impuesto',
            'tax',
            'vat',
            'iva',
            'i v a',
            'descuento',
            'discount',
        ];

        if (in_array($normalizedConcept, $exactMatches, true))
        {
            return true;
        }

        $prefixPatterns = [
            '/^iva\b/u',
            '/^i v a\b/u',
            '/^cuota\b/u',
            '/^impuesto\b/u',
            '/^retencion\b/u',
            '/^retenc\b/u',
            '/^irpf\b/u',
            '/^withholding\b/u',
            '/^base imponible\b/u',
            '/^subtotal\b/u',
        ];

        foreach ($prefixPatterns as $pattern)
        {
            if (preg_match($pattern, $normalizedConcept) === 1)
            {
                return true;
            }
        }

        return false;
    }

    private function amountsAreClose(float $left, float $right, float $tolerance = 0.02): bool
    {
        if ($right <= 0)
        {
            return false;
        }

        return abs($left - $right) <= $tolerance;
    }

    /**
     * @param  array<int, array<string, float|string>>  $lines
     */
    private function resolveTotalAmount(?float $totalAmount, array $lines): ?float
    {
        if ($totalAmount !== null && $totalAmount > 0)
        {
            return round($totalAmount, 2);
        }

        if ($lines === [])
        {
            return null;
        }

        $computedTotal = collect($lines)->sum(function (array $line): float
        {
            $baseAmount = (float) $line['base_amount'];
            $vatPercent = (float) $line['vat_percent'];
            $retentionPercent = (float) $line['retention_percent'];
            $allocationPercent = (float) $line['allocation_percent'];

            $vatAmount = $baseAmount * ($vatPercent / 100);
            $retentionAmount = $baseAmount * ($retentionPercent / 100);
            $lineTotal = ($baseAmount + $vatAmount - $retentionAmount) * ($allocationPercent / 100);

            return round($lineTotal, 2);
        });

        return round(max((float) $computedTotal, 0), 2);
    }

    private function normalizeText(?string $text): string
    {
        $value = Str::of((string) $text)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        return (string) $value;
    }

    /**
     * @return array<int, string>
     */
    private function resolveOwnCompanyNames(int $teamId): array
    {
        $team = Team::withoutGlobalScopes()->find($teamId);
        $businessConfig = $team?->getSetting('business_config', []);
        if (is_string($businessConfig))
        {
            $businessConfig = json_decode($businessConfig, true) ?: [];
        }

        return collect([
            $team?->name,
            is_array($businessConfig) ? ($businessConfig['business_name'] ?? null) : null,
            'REVISION ALPHA S.L.',
        ])
            ->filter(fn ($name): bool => is_string($name) && trim($name) !== '')
            ->map(fn (string $name): string => $this->normalizeText($name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isOwnCompanyCandidate(string $candidate, array $ownCompanyNames): bool
    {
        $normalizedCandidate = $this->normalizeText($candidate);
        if ($normalizedCandidate === '' || $ownCompanyNames === [])
        {
            return false;
        }

        foreach ($ownCompanyNames as $ownCompanyName)
        {
            if ($ownCompanyName === '')
            {
                continue;
            }

            if (
                $normalizedCandidate === $ownCompanyName
                || str_contains($normalizedCandidate, $ownCompanyName)
                || str_contains($ownCompanyName, $normalizedCandidate)
            ) {
                return true;
            }

            similar_text($normalizedCandidate, $ownCompanyName, $similarity);
            if ((float) $similarity >= 90.0)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function extractPhoneCandidates(string $text): array
    {
        preg_match_all('/(?<![A-Z0-9])(?:\+?\d[\d\s\-\(\)\.]{6,}\d)(?![A-Z0-9])/iu', $text, $matches);

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
                    $normalized = '+'.(preg_replace('/[^\d]/', '', substr($normalized, 1)) ?? '');
                } else
                {
                    $normalized = preg_replace('/[^\d]/', '', $normalized) ?? '';
                }

                $digitsLength = strlen(ltrim($normalized, '+'));
                if ($digitsLength < 8 || $digitsLength > 15)
                {
                    return null;
                }

                return $normalized;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isLikelyPhoneNumber(string $candidate, array $knownPhones): bool
    {
        if (preg_match('/[A-Z]/iu', $candidate) === 1)
        {
            return false;
        }

        $normalizedCandidate = preg_replace('/[^\d+]/', '', trim($candidate)) ?? '';
        if ($normalizedCandidate === '')
        {
            return true;
        }

        $digitsOnlyCandidate = ltrim($normalizedCandidate, '+');
        foreach ($knownPhones as $phone)
        {
            $digitsOnlyPhone = ltrim((string) $phone, '+');
            if ($digitsOnlyPhone !== '' && $digitsOnlyPhone === $digitsOnlyCandidate)
            {
                return true;
            }
        }

        if (str_starts_with($normalizedCandidate, '+') && strlen($digitsOnlyCandidate) >= 8 && strlen($digitsOnlyCandidate) <= 15)
        {
            return true;
        }

        return false;
    }

    /**
     * @return array<int, array{concept:string,base_amount:float,vat_percent:float,retention_percent:float,allocation_percent:int}>
     */
    private function extractInvoiceLines(
        string $text,
        float $defaultVatPercent,
        float $defaultRetentionPercent,
        ?float $totalAmount,
    ): array {
        $rows = preg_split('/\R+/', $text) ?: [];
        $detectedLines = [];
        $pendingConcept = null;

        foreach ($rows as $row)
        {
            $line = trim($row);
            if ($line === '')
            {
                continue;
            }

            if ($this->isSummaryOrMetaLine($line))
            {
                continue;
            }

            $conceptFromLine = $this->cleanConceptText($line);
            if ($conceptFromLine !== '' && $this->isTaxOrSummaryConcept($conceptFromLine))
            {
                continue;
            }

            $amounts = $this->extractAmountsFromLine($line);
            if ($amounts === [])
            {
                $conceptOnly = $this->cleanConceptText($line);
                if ($conceptOnly !== '' && mb_strlen($conceptOnly) >= 6)
                {
                    $pendingConcept = $conceptOnly;
                }

                continue;
            }

            $baseAmount = (float) ($amounts[0] ?? 0);
            if ($baseAmount <= 0)
            {
                continue;
            }

            if ($totalAmount !== null && $totalAmount > 0 && $baseAmount > ($totalAmount * 2) && count($amounts) === 1)
            {
                continue;
            }

            $concept = $this->cleanConceptText($line);
            if (($concept === '' || mb_strlen($concept) < 4) && $pendingConcept !== null)
            {
                $concept = $pendingConcept;
            }
            $pendingConcept = null;

            if ($concept === '')
            {
                continue;
            }

            $percentages = $this->extractPercentagesFromLine($line);
            $vatPercent = $defaultVatPercent;
            $retentionPercent = $defaultRetentionPercent;

            if ($percentages !== [])
            {
                $vatPercent = (float) ($percentages[0] ?? $defaultVatPercent);
                $retentionPercent = (float) ($percentages[1] ?? $defaultRetentionPercent);
            }

            if (preg_match('/\b(retenci[oó]n|retencion|irpf|withholding)\b/iu', $line) === 1 && $percentages !== [])
            {
                $retentionPercent = (float) ($percentages[count($percentages) - 1] ?? $defaultRetentionPercent);
            }

            $deduplicationKey = $this->normalizeText($concept).'|'.number_format($baseAmount, 2, '.', '');
            if (isset($detectedLines[$deduplicationKey]))
            {
                continue;
            }

            $detectedLines[$deduplicationKey] = [
                'concept' => Str::limit($concept, 255, ''),
                'base_amount' => round($baseAmount, 2),
                'vat_percent' => round(max($vatPercent, 0), 2),
                'retention_percent' => round(max($retentionPercent, 0), 2),
                'allocation_percent' => 100,
            ];
        }

        return array_values($detectedLines);
    }

    private function isSummaryOrMetaLine(string $line): bool
    {
        $normalizedLine = $this->normalizeText($line);

        if ($this->isTaxOrSummaryConcept($line))
        {
            return true;
        }

        return str_contains($normalizedLine, 'total')
            || str_contains($normalizedLine, 'subtotal')
            || str_contains($normalizedLine, 'factura')
            || str_contains($normalizedLine, 'invoice')
            || str_contains($normalizedLine, 'fecha')
            || str_contains($normalizedLine, 'vencimiento')
            || str_contains($normalizedLine, 'telefono')
            || str_contains($normalizedLine, 'nif')
            || str_contains($normalizedLine, 'cif')
            || str_contains($normalizedLine, 'iban')
            || str_contains($normalizedLine, 'cliente')
            || str_contains($normalizedLine, 'destinatario');
    }

    /**
     * @return array<int, float>
     */
    private function extractAmountsFromLine(string $line): array
    {
        preg_match_all('/[$€]?\s*\d{1,3}(?:[.\s]\d{3})*(?:[.,]\d{2})|[$€]?\s*\d+(?:[.,]\d{2})/u', $line, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $amount): float => round($this->toFloat($amount), 2))
            ->filter(fn (float $amount): bool => $amount > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, float>
     */
    private function extractPercentagesFromLine(string $line): array
    {
        preg_match_all('/([0-9]{1,2}(?:[.,][0-9]{1,2})?)\s*%/u', $line, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $percentage): float => round($this->toFloat($percentage), 2))
            ->filter(fn (float $percentage): bool => $percentage >= 0)
            ->values()
            ->all();
    }

    private function cleanConceptText(string $line): string
    {
        $concept = preg_replace('/[$€]?\s*\d{1,3}(?:[.\s]\d{3})*(?:[.,]\d{2})|[$€]?\s*\d+(?:[.,]\d{2})/u', ' ', $line) ?? $line;
        $concept = preg_replace('/([0-9]{1,2}(?:[.,][0-9]{1,2})?)\s*%/u', ' ', $concept) ?? $concept;
        $concept = str_replace(['|', ';', '  '], ' ', $concept);
        $concept = preg_replace('/\s+/', ' ', $concept) ?? $concept;

        return trim($concept);
    }
}
