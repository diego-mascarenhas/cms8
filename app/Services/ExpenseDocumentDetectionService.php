<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Team;
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
- Do not include markdown or explanations.
PROMPT;

    public function __construct(
        private readonly DocumentOcrService $ocrService,
        private readonly DocumentAiOcrService $aiOcrService,
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
        $heuristicData = $this->extractStructuredDataWithHeuristics($ocrResult['text']);
        $aiData = $this->extractStructuredDataWithAi($ocrResult['text'], $teamId, $ocrResult['mode']);
        $detectedData = $this->mergeDetectedData($heuristicData, $aiData);

        $enterpriseId = $this->resolveEnterpriseId($detectedData['enterprise_name'] ?? null, $teamId);
        $currencyId = $this->resolveCurrencyId($detectedData['currency_code'] ?? null);
        $lines = $this->normalizeLines($detectedData['lines'] ?? [], $detectedData['total_amount'] ?? null);
        $totalAmount = $this->resolveTotalAmount($detectedData['total_amount'] ?? null, $lines);

        return [
            'enterprise_id' => $enterpriseId,
            'enterprise_name' => $detectedData['enterprise_name'] ?? null,
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
    private function extractStructuredDataWithHeuristics(?string $ocrText): array
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

        $enterpriseName = $this->extractEnterpriseName($text);
        $documentNumber = $this->extractDocumentNumber($text);
        $invoiceDate = $this->extractDateByLabel($text, ['fecha factura', 'fecha', 'invoice date', 'date']);
        $dueDate = $this->extractDateByLabel($text, ['fecha vencimiento', 'vencimiento', 'due date', 'payment due']);
        $currencyCode = $this->extractCurrencyCode($text);
        $vatPercent = $this->extractPercentageByLabel($text, ['iva', 'vat']);
        $retentionPercent = $this->extractPercentageByLabel($text, ['retención', 'retencion', 'withholding']);
        $totalAmount = $this->extractTotalAmount($text);

        $lines = [];
        if ($totalAmount !== null && $totalAmount > 0)
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
            )->prompt($text, [], provider: 'anthropic');

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

        if (! empty($aiData['lines']) && is_array($aiData['lines']))
        {
            $merged['lines'] = $aiData['lines'];
        }

        return $merged;
    }

    private function extractEnterpriseName(string $text): ?string
    {
        $patterns = [
            '/(?:empresa|proveedor|raz[oó]n social|supplier|vendor)\s*[:\-]\s*([^\r\n]+)/iu',
        ];

        foreach ($patterns as $pattern)
        {
            if (preg_match($pattern, $text, $matches) === 1)
            {
                $candidate = trim((string) ($matches[1] ?? ''));
                if ($candidate !== '')
                {
                    return Str::limit($candidate, 255, '');
                }
            }
        }

        $lines = preg_split('/\R+/', $text) ?: [];
        foreach ($lines as $line)
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
            ) {
                continue;
            }

            return Str::limit($candidate, 255, '');
        }

        return null;
    }

    private function extractDocumentNumber(string $text): ?string
    {
        $patterns = [
            '/(?:n[úu]m(?:ero)?(?:\s+de)?(?:\s+factura)?|invoice(?:\s+no|\s+number)?|factura(?:\s+n[ºo])?)\s*[:#\-]?\s*([A-Z0-9\-\/\.]{3,})/iu',
        ];

        foreach ($patterns as $pattern)
        {
            if (preg_match($pattern, $text, $matches) === 1)
            {
                $candidate = trim((string) ($matches[1] ?? ''));
                if ($candidate !== '')
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

    private function resolveEnterpriseId(?string $enterpriseName, int $teamId): ?int
    {
        $normalizedTarget = $this->normalizeText($enterpriseName);
        if ($normalizedTarget === '')
        {
            return null;
        }

        $enterprises = Enterprise::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->get(['id', 'name', 'type_id']);

        $bestEnterpriseId = null;
        $bestScore = 0.0;

        foreach ($enterprises as $enterprise)
        {
            $normalizedName = $this->normalizeText($enterprise->name);
            if ($normalizedName === '')
            {
                continue;
            }

            $score = 0.0;
            if ($normalizedName === $normalizedTarget)
            {
                $score = 100.0;
            } elseif (str_contains($normalizedName, $normalizedTarget) || str_contains($normalizedTarget, $normalizedName))
            {
                $score = 85.0;
            } else
            {
                similar_text($normalizedName, $normalizedTarget, $score);
            }

            if ((int) $enterprise->type_id === 2)
            {
                $score += 5.0;
            }

            if ($score > $bestScore)
            {
                $bestScore = $score;
                $bestEnterpriseId = (int) $enterprise->id;
            }
        }

        if ($bestScore < 55.0)
        {
            return null;
        }

        return $bestEnterpriseId;
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
}
