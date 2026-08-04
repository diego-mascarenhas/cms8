<?php

namespace App\Services;

use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\Team;
use App\Support\AiTasks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use function Laravel\Ai\agent;

class ExpenseSupplierService
{
    private const VISION_SUPPLIER_INSTRUCTIONS = <<<'PROMPT'
You analyze a purchase invoice document image.
Identify the SUPPLIER/ISSUER (seller), not the customer/recipient/buyer.
Use the logo, branding, letterhead, and header area first, then the supplier block.
Return ONLY valid JSON with this structure:
{
  "brand_name": "string|null",
  "legal_name": "string|null",
  "identification_number": "string|null",
  "email": "string|null",
  "phone": "string|null",
  "website": "string|null",
  "address": "string|null",
  "postal_code": "string|null",
  "locality": "string|null",
  "province": "string|null",
  "country": "ISO2|null"
}
Rules:
- Use null when unknown.
- identification_number MUST be the supplier tax id (NIF/CIF/VAT), never the customer/buyer tax id.
- phone MUST be the supplier phone only (9-15 digits), never the customer/buyer phone or invoice/document number.
- Do not include markdown or explanations.
PROMPT;

    /**
     * @param  array<string, mixed>  $invoiceData
     * @return array{
     *     supplier: array<string, string|null>,
     *     enterprise_id: int|null,
     *     enterprise_name: string|null,
     *     match: array{status: string, source: string|null, confidence: float}
     * }
     */
    public function resolveForDetectedInvoice(
        UploadedFile $file,
        ?string $ocrText,
        array $invoiceData,
        int $teamId,
        string $ocrMode,
    ): array {
        $visionSupplier = $this->extractSupplierWithVision($file, $teamId, $ocrMode);
        $textSupplier = $this->extractSupplierFromOcrText($ocrText, $teamId);
        $supplier = $this->mergeSupplierData($visionSupplier, $textSupplier, $invoiceData);
        $supplier = $this->sanitizeSupplierAgainstOwnBusiness($supplier, $ocrText, $teamId);
        $match = $this->matchEnterprise($supplier, $teamId);

        return [
            'supplier' => $supplier,
            'enterprise_id' => $match['enterprise_id'],
            'enterprise_name' => $match['enterprise_name'] ?? $this->preferredSupplierName($supplier),
            'match' => [
                'status' => $match['status'],
                'source' => $match['source'],
                'confidence' => $match['confidence'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $supplierData
     */
    public function matchesOwnBusiness(array $supplierData, int $teamId): bool
    {
        $profile = $this->resolveOwnBusinessProfile($teamId);

        $name = trim((string) ($supplierData['name'] ?? ''));
        if ($name !== '' && $this->isOwnCompanyCandidate($name, $profile['names']))
        {
            return true;
        }

        $taxId = $this->normalizeTaxId((string) ($supplierData['identification_number'] ?? ''));
        if ($taxId !== '' && in_array($taxId, $profile['tax_ids'], true))
        {
            return true;
        }

        $email = $this->normalizeEmail((string) ($supplierData['email'] ?? ''));
        if ($email !== '' && in_array($email, $profile['emails'], true))
        {
            return true;
        }

        $phone = $this->normalizePhone((string) ($supplierData['phone'] ?? ''));
        if ($phone !== '' && $this->phoneMatchesExcluded($phone, $profile['phones']))
        {
            return true;
        }

        $website = $this->normalizeWebsite((string) ($supplierData['website'] ?? ''));
        if ($website !== '' && in_array($website, $profile['websites'], true))
        {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $supplierData
     */
    public function createSupplier(int $teamId, array $supplierData): Enterprise
    {
        return $this->createCounterparty($teamId, $supplierData, 2, 'proveedor');
    }

    /**
     * @param  array<string, mixed>  $clientData
     */
    public function createClient(int $teamId, array $clientData): Enterprise
    {
        return $this->createCounterparty($teamId, $clientData, 1, 'cliente');
    }

    /**
     * @param  array<string, mixed>  $counterpartyData
     */
    private function createCounterparty(
        int $teamId,
        array $counterpartyData,
        int $typeId,
        string $counterpartyLabel,
    ): Enterprise {
        if ($this->matchesOwnBusiness($counterpartyData, $teamId))
        {
            throw new \InvalidArgumentException(
                "Los datos corresponden a la configuración de tu negocio. Indica los del {$counterpartyLabel}.",
            );
        }

        $name = trim((string) ($counterpartyData['name'] ?? ''));
        if ($name === '')
        {
            throw new \InvalidArgumentException("El nombre del {$counterpartyLabel} es obligatorio.");
        }

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'type_id' => $typeId,
            'status_id' => 2,
            'name' => Str::limit($name, 75, ''),
            'email' => filled($counterpartyData['email'] ?? null) ? trim((string) $counterpartyData['email']) : null,
            'phone' => filled($counterpartyData['phone'] ?? null) ? trim((string) $counterpartyData['phone']) : null,
            'website' => filled($counterpartyData['website'] ?? null) ? trim((string) $counterpartyData['website']) : null,
            'address' => filled($counterpartyData['address'] ?? null) ? trim((string) $counterpartyData['address']) : null,
            'postal_code' => filled($counterpartyData['postal_code'] ?? null) ? trim((string) $counterpartyData['postal_code']) : null,
            'locality' => filled($counterpartyData['locality'] ?? null) ? trim((string) $counterpartyData['locality']) : null,
            'province' => filled($counterpartyData['province'] ?? null) ? trim((string) $counterpartyData['province']) : null,
            'country' => strtoupper(trim((string) ($counterpartyData['country'] ?? 'ES'))) ?: 'ES',
        ]);

        $taxId = $this->normalizeTaxId((string) ($counterpartyData['identification_number'] ?? ''));
        if ($taxId !== '')
        {
            EnterpriseBillingAddress::query()->create([
                'enterprise_id' => $enterprise->id,
                'name' => $enterprise->name,
                'tax_status_type_id' => 1,
                'identification_number' => $taxId,
                'address' => $enterprise->address,
                'postal_code' => $enterprise->postal_code,
                'locality' => $enterprise->locality,
                'province' => $enterprise->province,
                'country' => $enterprise->country,
                'status' => 1,
            ]);
        }

        return $enterprise;
    }

    /**
     * @return array<string, string|null>|null
     */
    private function extractSupplierWithVision(UploadedFile $file, int $teamId, string $ocrMode): ?array
    {
        if (! in_array($ocrMode, ['ai', 'hybrid'], true))
        {
            return null;
        }

        $absolutePath = $file->getRealPath();
        if (! is_string($absolutePath) || $absolutePath === '' || ! is_file($absolutePath))
        {
            return null;
        }

        try
        {
            $uploadedFile = new UploadedFile(
                $absolutePath,
                $file->getClientOriginalName(),
                $file->getMimeType() ?: mime_content_type($absolutePath) ?: null,
                null,
                true,
            );

            $response = agent(
                instructions: self::VISION_SUPPLIER_INSTRUCTIONS,
                messages: [],
                tools: [],
            )->prompt(
                'Identify the supplier on this purchase invoice. Prioritize logo and branding.',
                [$uploadedFile],
                provider: AiTasks::provider('vision'),
            );

            TokenUsageLogService::logFromAiResponse(
                teamId: $teamId,
                service: 'ExpenseSupplierService',
                usage: $response->usage ?? null,
                moduleKey: 'invoices',
            );

            $decoded = $this->decodeJsonResponse((string) ($response->text ?? ''));

            return is_array($decoded) ? $this->normalizeSupplierShape($decoded) : null;
        } catch (\Throwable $exception)
        {
            Log::warning('Expense supplier vision extraction failed', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function extractSupplierFromOcrText(?string $ocrText, int $teamId): array
    {
        $text = trim((string) $ocrText);
        if ($text === '')
        {
            return $this->emptySupplier();
        }

        $ownCompanyNames = $this->resolveOwnCompanyNames($teamId);

        return $this->normalizeSupplierShape([
            'legal_name' => $this->extractLabeledValue($text, ['proveedor', 'emisor', 'supplier', 'vendor', 'razón social emisor']),
            'identification_number' => $this->extractTaxIdFromText($text, $teamId),
            'email' => $this->extractLabeledValue($this->extractSupplierSectionText($text), ['email', 'correo']),
            'phone' => $this->extractSupplierPhoneFromText($text, $teamId),
            'address' => $this->extractLabeledValue($this->extractSupplierSectionText($text), ['dirección', 'direccion', 'address']),
        ], $ownCompanyNames);
    }

    /**
     * @param  array<string, string|null>  $supplier
     * @return array<string, string|null>
     */
    private function sanitizeSupplierAgainstOwnBusiness(array $supplier, ?string $ocrText, int $teamId): array
    {
        $profile = $this->resolveOwnBusinessProfile($teamId);
        $excludedTaxIds = $this->resolveExcludedTaxIds((string) $ocrText, $teamId, $profile);

        $taxId = $this->normalizeTaxId((string) ($supplier['identification_number'] ?? ''));
        if ($taxId !== '' && (in_array($taxId, $excludedTaxIds, true) || in_array($taxId, $profile['tax_ids'], true)))
        {
            $supplier['identification_number'] = null;
        }

        foreach (['legal_name', 'brand_name'] as $nameKey)
        {
            $name = trim((string) ($supplier[$nameKey] ?? ''));
            if ($name !== '' && $this->isOwnCompanyCandidate($name, $profile['names']))
            {
                $supplier[$nameKey] = null;
            }
        }

        $email = $this->normalizeEmail((string) ($supplier['email'] ?? ''));
        if ($email !== '' && in_array($email, $profile['emails'], true))
        {
            $supplier['email'] = null;
        }

        $phone = $this->normalizePhone((string) ($supplier['phone'] ?? ''));
        $excludedPhones = $this->resolveExcludedPhones((string) $ocrText, $teamId, $profile);
        $rawPhone = trim((string) ($supplier['phone'] ?? ''));

        if ($rawPhone !== '' && ! $this->isValidSupplierPhone($rawPhone))
        {
            $supplier['phone'] = null;
        } elseif ($phone !== '' && $this->phoneMatchesExcluded($phone, $excludedPhones))
        {
            $supplier['phone'] = null;
        } elseif ($phone !== '' && $taxId !== '' && $this->phoneOverlapsTaxId($phone, $taxId))
        {
            $supplier['phone'] = null;
        }

        $website = $this->normalizeWebsite((string) ($supplier['website'] ?? ''));
        if ($website !== '' && in_array($website, $profile['websites'], true))
        {
            $supplier['website'] = null;
        }

        $address = $this->normalizeText((string) ($supplier['address'] ?? ''));
        if ($address !== '' && $this->matchesOwnAddressValue($address, $profile['addresses']))
        {
            $supplier['address'] = null;
        }

        $postalCode = $this->normalizePostalCode((string) ($supplier['postal_code'] ?? ''));
        if ($postalCode !== '' && in_array($postalCode, $profile['postal_codes'], true))
        {
            $supplier['postal_code'] = null;
        }

        $locality = $this->normalizeText((string) ($supplier['locality'] ?? ''));
        if ($locality !== '' && $this->matchesOwnAddressValue($locality, $profile['localities']))
        {
            $supplier['locality'] = null;
        }

        return $supplier;
    }

    /**
     * @param  array{
     *     names: array<int, string>,
     *     tax_ids: array<int, string>,
     *     emails: array<int, string>,
     *     phones: array<int, string>,
     *     websites: array<int, string>,
     *     addresses: array<int, string>,
     *     postal_codes: array<int, string>,
     *     localities: array<int, string>
     * }  $profile
     * @return array<int, string>
     */
    private function resolveExcludedTaxIds(string $text, int $teamId, ?array $profile = null): array
    {
        $profile ??= $this->resolveOwnBusinessProfile($teamId);
        $excluded = $profile['tax_ids'];

        foreach (['cliente', 'destinatario', 'comprador', 'customer', 'buyer', 'recipient', 'receptor', 'facturar a', 'bill to'] as $label)
        {
            $taxId = $this->extractTaxIdNearLabel($text, $label);
            if ($taxId !== null)
            {
                $excluded[] = $taxId;
            }
        }

        return array_values(array_unique($excluded));
    }

    private function extractSupplierSectionText(string $text): string
    {
        $markers = ['cliente', 'destinatario', 'comprador', 'customer', 'buyer', 'facturar a', 'bill to'];
        $lowerText = Str::lower($text);
        $cutPosition = mb_strlen($text);

        foreach ($markers as $marker)
        {
            $position = mb_stripos($lowerText, $marker);
            if ($position !== false && $position < $cutPosition)
            {
                $cutPosition = $position;
            }
        }

        return trim(mb_substr($text, 0, $cutPosition));
    }

    private function extractBuyerSectionText(string $text): string
    {
        $markers = ['cliente', 'destinatario', 'comprador', 'customer', 'buyer', 'facturar a', 'bill to', 'recipient', 'receptor'];
        $lowerText = Str::lower($text);
        $startPosition = null;

        foreach ($markers as $marker)
        {
            $position = mb_stripos($lowerText, $marker);
            if ($position !== false && ($startPosition === null || $position < $startPosition))
            {
                $startPosition = $position;
            }
        }

        if ($startPosition === null)
        {
            return '';
        }

        return trim(mb_substr($text, $startPosition));
    }

    private function extractSupplierPhoneFromText(string $text, int $teamId): ?string
    {
        $supplierSection = $this->extractSupplierSectionText($text);
        $labeledValue = $this->extractLabeledValue($supplierSection, ['teléfono', 'telefono', 'phone', 'móvil', 'movil']);
        $candidate = $labeledValue !== null ? $this->extractPhoneToken($labeledValue) : null;

        if ($candidate === null)
        {
            return null;
        }

        $profile = $this->resolveOwnBusinessProfile($teamId);
        $excludedPhones = $this->resolveExcludedPhones($text, $teamId, $profile);
        $normalizedPhone = $this->normalizePhone($candidate);

        if ($normalizedPhone !== '' && $this->phoneMatchesExcluded($normalizedPhone, $excludedPhones))
        {
            return null;
        }

        return $candidate;
    }

    private function extractPhoneToken(string $value): ?string
    {
        $value = trim($value);
        if ($value === '')
        {
            return null;
        }

        if ($this->isValidSupplierPhone($value))
        {
            return $value;
        }

        if (preg_match('/(\+?\d[\d\s()\-]{7,}\d)/', $value, $matches) !== 1)
        {
            return null;
        }

        $candidate = trim((string) ($matches[1] ?? ''));

        return $this->isValidSupplierPhone($candidate) ? $candidate : null;
    }

    /**
     * @return array<int, string>
     */
    private function extractPhonesFromText(string $text): array
    {
        if (trim($text) === '')
        {
            return [];
        }

        $phones = [];

        if (preg_match_all('/(?:\+?\d[\d\s()\-]{7,}\d)/', $text, $matches) > 0)
        {
            foreach ($matches[0] as $match)
            {
                $candidate = trim((string) $match);
                if ($this->isValidSupplierPhone($candidate))
                {
                    $phones[] = $candidate;
                }
            }
        }

        return array_values(array_unique($phones));
    }

    /**
     * @param  array{
     *     names: array<int, string>,
     *     tax_ids: array<int, string>,
     *     emails: array<int, string>,
     *     phones: array<int, string>,
     *     websites: array<int, string>,
     *     addresses: array<int, string>,
     *     postal_codes: array<int, string>,
     *     localities: array<int, string>
     * }  $profile
     * @return array<int, string>
     */
    private function resolveExcludedPhones(string $text, int $teamId, ?array $profile = null): array
    {
        $profile ??= $this->resolveOwnBusinessProfile($teamId);
        $excluded = $profile['phones'];

        $buyerText = $this->extractBuyerSectionText($text);
        if ($buyerText !== '')
        {
            foreach ($this->extractPhonesFromText($buyerText) as $phone)
            {
                $normalized = $this->normalizePhone($phone);
                if ($normalized !== '')
                {
                    $excluded[] = $normalized;
                }
            }
        }

        return array_values(array_unique(array_filter($excluded)));
    }

    /**
     * @param  array<int, string>  $excludedPhones
     */
    private function phoneMatchesExcluded(string $normalizedPhone, array $excludedPhones): bool
    {
        if ($normalizedPhone === '')
        {
            return false;
        }

        foreach ($excludedPhones as $excludedPhone)
        {
            if ($excludedPhone === '')
            {
                continue;
            }

            if ($normalizedPhone === $excludedPhone)
            {
                return true;
            }

            if (strlen($normalizedPhone) >= 9
                && strlen($excludedPhone) >= 9
                && substr($normalizedPhone, -9) === substr($excludedPhone, -9))
            {
                return true;
            }
        }

        return false;
    }

    private function isValidSupplierPhone(string $value): bool
    {
        $value = trim($value);
        if ($value === '')
        {
            return false;
        }

        if (preg_match('/^[+\-\d\s()]+$/', $value) !== 1)
        {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 9 && strlen($digits) <= 15;
    }

    private function phoneOverlapsTaxId(string $normalizedPhone, string $taxId): bool
    {
        $taxDigits = preg_replace('/\D+/', '', $taxId) ?? '';
        if ($taxDigits === '' || strlen($taxDigits) < 7)
        {
            return false;
        }

        return str_contains($taxDigits, $normalizedPhone)
            || str_contains($normalizedPhone, $taxDigits);
    }

    /**
     * @param  array<string, string|null>|null  $visionSupplier
     * @param  array<string, string|null>  $textSupplier
     * @param  array<string, mixed>  $invoiceData
     * @return array<string, string|null>
     */
    private function mergeSupplierData(?array $visionSupplier, array $textSupplier, array $invoiceData): array
    {
        $merged = $this->emptySupplier();

        foreach ([$textSupplier, $visionSupplier ?? [], [
            'legal_name' => $invoiceData['enterprise_name'] ?? null,
            'brand_name' => null,
        ]] as $source)
        {
            foreach ($source as $key => $value)
            {
                if (! is_string($key))
                {
                    continue;
                }

                if (filled($value) && blank($merged[$key] ?? null))
                {
                    $merged[$key] = trim((string) $value);
                }
            }
        }

        if (blank($merged['legal_name']) && filled($merged['brand_name']))
        {
            $merged['legal_name'] = $merged['brand_name'];
        }

        if (filled($merged['identification_number']))
        {
            $merged['identification_number'] = $this->normalizeTaxId((string) $merged['identification_number']);
        }

        if (filled($merged['country']))
        {
            $merged['country'] = strtoupper((string) $merged['country']);
        }

        if (filled($merged['phone']) && ! $this->isValidSupplierPhone((string) $merged['phone']))
        {
            $merged['phone'] = null;
        }

        return $merged;
    }

    /**
     * @param  array<string, string|null>  $supplier
     * @return array{enterprise_id: int|null, enterprise_name: string|null, status: string, source: string|null, confidence: float}
     */
    private function matchEnterprise(array $supplier, int $teamId): array
    {
        $taxId = $this->normalizeTaxId((string) ($supplier['identification_number'] ?? ''));
        if ($taxId !== '')
        {
            $billingMatches = EnterpriseBillingAddress::query()
                ->whereHas('enterprise', function ($query) use ($teamId): void
                {
                    $query->where('team_id', $teamId);
                })
                ->with('enterprise:id,name,type_id')
                ->get();

            foreach ($billingMatches as $billingMatch)
            {
                if ($this->normalizeTaxId((string) $billingMatch->identification_number) !== $taxId)
                {
                    continue;
                }

                if ($billingMatch->enterprise)
                {
                    return [
                        'enterprise_id' => (int) $billingMatch->enterprise->id,
                        'enterprise_name' => (string) $billingMatch->enterprise->name,
                        'status' => 'matched',
                        'source' => 'tax_id',
                        'confidence' => 100.0,
                    ];
                }
            }
        }

        $candidates = collect([
            ['name' => (string) ($supplier['legal_name'] ?? ''), 'source' => 'name', 'bonus' => 0.0],
            ['name' => (string) ($supplier['brand_name'] ?? ''), 'source' => 'logo', 'bonus' => 8.0],
        ])->filter(fn (array $candidate): bool => trim($candidate['name']) !== '');

        $bestEnterpriseId = null;
        $bestEnterpriseName = null;
        $bestScore = 0.0;
        $bestSource = null;

        $enterprises = Enterprise::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->get(['id', 'name', 'type_id']);

        foreach ($candidates as $candidate)
        {
            $normalizedTarget = $this->normalizeText($candidate['name']);
            if ($normalizedTarget === '')
            {
                continue;
            }

            foreach ($enterprises as $enterprise)
            {
                $score = $this->scoreEnterpriseNameMatch($normalizedTarget, (string) $enterprise->name);
                if ((int) $enterprise->type_id === 2)
                {
                    $score += 5.0;
                }
                $score += (float) $candidate['bonus'];

                if ($score > $bestScore)
                {
                    $bestScore = $score;
                    $bestEnterpriseId = (int) $enterprise->id;
                    $bestEnterpriseName = (string) $enterprise->name;
                    $bestSource = (string) $candidate['source'];
                }
            }
        }

        if ($bestScore >= 80.0)
        {
            return [
                'enterprise_id' => $bestEnterpriseId,
                'enterprise_name' => $bestEnterpriseName,
                'status' => 'matched',
                'source' => $bestSource,
                'confidence' => round($bestScore, 2),
            ];
        }

        if ($bestScore >= 55.0)
        {
            return [
                'enterprise_id' => $bestEnterpriseId,
                'enterprise_name' => $bestEnterpriseName,
                'status' => 'suggested',
                'source' => $bestSource,
                'confidence' => round($bestScore, 2),
            ];
        }

        return [
            'enterprise_id' => null,
            'enterprise_name' => $this->preferredSupplierName($supplier),
            'status' => 'unmatched',
            'source' => null,
            'confidence' => 0.0,
        ];
    }

    private function scoreEnterpriseNameMatch(string $normalizedTarget, string $enterpriseName): float
    {
        $normalizedName = $this->normalizeText($enterpriseName);
        if ($normalizedName === '')
        {
            return 0.0;
        }

        if ($normalizedName === $normalizedTarget)
        {
            return 100.0;
        }

        if (str_contains($normalizedName, $normalizedTarget) || str_contains($normalizedTarget, $normalizedName))
        {
            return 88.0;
        }

        similar_text($normalizedName, $normalizedTarget, $score);

        return (float) $score;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $ownCompanyNames
     * @return array<string, string|null>
     */
    private function normalizeSupplierShape(array $payload, array $ownCompanyNames = []): array
    {
        $supplier = $this->emptySupplier();
        $map = [
            'brand_name' => ['brand_name', 'brand', 'trade_name', 'tradename'],
            'legal_name' => ['legal_name', 'business_name', 'company_name', 'name', 'enterprise_name'],
            'identification_number' => ['identification_number', 'tax_id', 'vat_id', 'nif', 'cif', 'vat_number'],
            'email' => ['email'],
            'phone' => ['phone', 'telephone'],
            'website' => ['website', 'url'],
            'address' => ['address', 'street'],
            'postal_code' => ['postal_code', 'zip', 'zip_code'],
            'locality' => ['locality', 'city', 'town'],
            'province' => ['province', 'region', 'state'],
            'country' => ['country', 'country_code'],
        ];

        foreach ($map as $targetKey => $sourceKeys)
        {
            foreach ($sourceKeys as $sourceKey)
            {
                $value = Arr::get($payload, $sourceKey);
                if (filled($value))
                {
                    $supplier[$targetKey] = trim((string) $value);
                    break;
                }
            }
        }

        if ($ownCompanyNames !== [] && filled($supplier['legal_name']) && $this->isOwnCompanyCandidate((string) $supplier['legal_name'], $ownCompanyNames))
        {
            $supplier['legal_name'] = null;
        }

        if ($ownCompanyNames !== [] && filled($supplier['brand_name']) && $this->isOwnCompanyCandidate((string) $supplier['brand_name'], $ownCompanyNames))
        {
            $supplier['brand_name'] = null;
        }

        return $supplier;
    }

    /**
     * @return array<string, string|null>
     */
    private function emptySupplier(): array
    {
        return [
            'brand_name' => null,
            'legal_name' => null,
            'identification_number' => null,
            'email' => null,
            'phone' => null,
            'website' => null,
            'address' => null,
            'postal_code' => null,
            'locality' => null,
            'province' => null,
            'country' => null,
        ];
    }

    /**
     * @param  array<string, string|null>  $supplier
     */
    private function preferredSupplierName(array $supplier): ?string
    {
        foreach (['legal_name', 'brand_name'] as $key)
        {
            if (filled($supplier[$key] ?? null))
            {
                return (string) $supplier[$key];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonResponse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '')
        {
            return null;
        }

        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $raw) ?? $raw;
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function extractTaxIdFromText(string $text, int $teamId): ?string
    {
        $excludedTaxIds = $this->resolveExcludedTaxIds($text, $teamId);
        $supplierText = $this->extractSupplierSectionText($text);

        foreach (['proveedor', 'emisor', 'supplier', 'vendor', 'issuer', 'vendedor'] as $label)
        {
            $candidate = $this->extractTaxIdNearLabel($supplierText, $label);
            if ($candidate !== null && ! in_array($candidate, $excludedTaxIds, true))
            {
                return $candidate;
            }
        }

        foreach ($this->extractAllTaxIdsFromText($supplierText) as $candidate)
        {
            if (! in_array($candidate, $excludedTaxIds, true))
            {
                return $candidate;
            }
        }

        return null;
    }

    private function extractTaxIdNearLabel(string $text, string $label): ?string
    {
        $patterns = [
            '/'.preg_quote($label, '/').'[^\r\n]{0,120}?(?:nif|cif|vat|n\.?\s*i\.?\s*f\.?|c\.?\s*i\.?\s*f\.?|identificaci[oó]n fiscal)\s*[:\-]?\s*([A-Z0-9][A-Z0-9\-\.\/]{6,14})/iu',
            '/(?:nif|cif|vat|n\.?\s*i\.?\s*f\.?|c\.?\s*i\.?\s*f\.?|identificaci[oó]n fiscal)\s*[:\-]?\s*([A-Z0-9][A-Z0-9\-\.\/]{6,14})[^\r\n]{0,120}?'.preg_quote($label, '/').'/iu',
        ];

        foreach ($patterns as $pattern)
        {
            if (preg_match($pattern, $text, $matches) === 1)
            {
                $candidate = $this->normalizeTaxId((string) ($matches[1] ?? ''));
                if ($candidate !== '')
                {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function extractAllTaxIdsFromText(string $text): array
    {
        $patterns = [
            '/(?:nif|cif|vat|n\.?\s*i\.?\s*f\.?|c\.?\s*i\.?\s*f\.?|identificaci[oó]n fiscal)\s*[:\-]?\s*([A-Z0-9][A-Z0-9\-\.\/]{6,14})/iu',
            '/\b([ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J])\b/i',
            '/\b([0-9]{8}[A-Z])\b/u',
        ];

        $taxIds = [];

        foreach ($patterns as $pattern)
        {
            if (preg_match_all($pattern, $text, $matches) < 1)
            {
                continue;
            }

            foreach ($matches[1] ?? [] as $rawTaxId)
            {
                $candidate = $this->normalizeTaxId((string) $rawTaxId);
                if ($candidate !== '')
                {
                    $taxIds[] = $candidate;
                }
            }
        }

        return array_values(array_unique($taxIds));
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function extractLabeledValue(string $text, array $labels): ?string
    {
        foreach ($labels as $label)
        {
            $pattern = '/'.preg_quote($label, '/').'\s*[:\-]\s*([^\r\n]+)/iu';
            if (preg_match($pattern, $text, $matches) === 1)
            {
                $value = trim((string) ($matches[1] ?? ''));
                if ($value !== '')
                {
                    return Str::limit($value, 255, '');
                }
            }
        }

        return null;
    }

    private function normalizeTaxId(string $value): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $value) ?? '');

        return strlen($normalized) >= 8 ? $normalized : '';
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
        return $this->resolveOwnBusinessProfile($teamId)['names'];
    }

    /**
     * @return array{
     *     names: array<int, string>,
     *     tax_ids: array<int, string>,
     *     emails: array<int, string>,
     *     phones: array<int, string>,
     *     websites: array<int, string>,
     *     addresses: array<int, string>,
     *     postal_codes: array<int, string>,
     *     localities: array<int, string>
     * }
     */
    private function resolveOwnBusinessProfile(int $teamId): array
    {
        $team = Team::withoutGlobalScopes()->with('owner')->find($teamId);
        $businessConfig = $team?->getSetting('business_config', []);
        if (is_string($businessConfig))
        {
            $businessConfig = json_decode($businessConfig, true) ?: [];
        }
        if (! is_array($businessConfig))
        {
            $businessConfig = [];
        }

        $names = collect([
            $team?->name,
            $businessConfig['business_name'] ?? null,
        ]);
        $emails = collect([
            $businessConfig['business_email'] ?? null,
            $businessConfig['contact_email'] ?? null,
            $team?->owner?->email,
        ]);
        $phones = collect([
            $businessConfig['business_phone'] ?? null,
            $businessConfig['business_whatsapp'] ?? null,
        ]);
        $websites = collect([
            $businessConfig['business_website'] ?? null,
        ]);
        $addresses = collect([
            $businessConfig['business_location'] ?? null,
            $businessConfig['address'] ?? null,
        ]);
        $postalCodes = collect([
            $businessConfig['business_postal_code'] ?? null,
            $businessConfig['pincode'] ?? null,
        ]);
        $localities = collect([
            $businessConfig['city'] ?? null,
        ]);
        $taxIds = collect();

        $ownCompanyNames = $names
            ->filter(fn ($name): bool => is_string($name) && trim($name) !== '')
            ->map(fn (string $name): string => $this->normalizeText($name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ownCompanyNames !== [])
        {
            $enterprises = Enterprise::withoutGlobalScopes()
                ->where('team_id', $teamId)
                ->with('enterpriseBillingAddresses')
                ->get();

            foreach ($enterprises as $enterprise)
            {
                if (! $this->isOwnCompanyCandidate((string) $enterprise->name, $ownCompanyNames))
                {
                    continue;
                }

                $names->push($enterprise->name);
                $emails->push($enterprise->email);
                $phones->push($enterprise->phone, $enterprise->whatsapp);
                $websites->push($enterprise->website);
                $addresses->push($enterprise->address);
                $postalCodes->push($enterprise->postal_code);
                $localities->push($enterprise->locality);

                foreach ($enterprise->enterpriseBillingAddresses as $billingAddress)
                {
                    $taxIds->push($billingAddress->identification_number);
                    $addresses->push($billingAddress->address);
                    $postalCodes->push($billingAddress->postal_code);
                    $localities->push($billingAddress->locality);
                }
            }
        }

        return [
            'names' => $names
                ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => $this->normalizeText($value))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'tax_ids' => $taxIds
                ->map(fn (mixed $value): string => $this->normalizeTaxId((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'emails' => $emails
                ->map(fn (mixed $value): string => $this->normalizeEmail((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'phones' => $phones
                ->map(fn (mixed $value): string => $this->normalizePhone((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'websites' => $websites
                ->map(fn (mixed $value): string => $this->normalizeWebsite((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'addresses' => $addresses
                ->map(fn (mixed $value): string => $this->normalizeText((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'postal_codes' => $postalCodes
                ->map(fn (mixed $value): string => $this->normalizePostalCode((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'localities' => $localities
                ->map(fn (mixed $value): string => $this->normalizeText((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function normalizeEmail(string $value): string
    {
        return Str::lower(trim($value));
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) < 9)
        {
            return '';
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '34'))
        {
            return substr($digits, 2);
        }

        if (strlen($digits) === 9)
        {
            return $digits;
        }

        return substr($digits, -9);
    }

    private function normalizeWebsite(string $value): string
    {
        $value = Str::lower(trim($value));
        if ($value === '')
        {
            return '';
        }

        $value = preg_replace('#^https?://#', '', $value) ?? $value;
        $value = preg_replace('#^www\.#', '', $value) ?? $value;

        return rtrim($value, '/');
    }

    private function normalizePostalCode(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');
    }

    /**
     * @param  array<int, string>  $ownValues
     */
    private function matchesOwnAddressValue(string $normalizedValue, array $ownValues): bool
    {
        if ($normalizedValue === '' || $ownValues === [])
        {
            return false;
        }

        foreach ($ownValues as $ownValue)
        {
            if ($ownValue === '')
            {
                continue;
            }

            if ($normalizedValue === $ownValue || str_contains($normalizedValue, $ownValue) || str_contains($ownValue, $normalizedValue))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $ownCompanyNames
     */
    private function isOwnCompanyCandidate(string $candidate, array $ownCompanyNames): bool
    {
        $normalizedCandidate = $this->normalizeText($candidate);
        if ($normalizedCandidate === '')
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
        }

        return false;
    }
}
