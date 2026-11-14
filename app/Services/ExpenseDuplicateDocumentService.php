<?php

namespace App\Services;

use App\Models\Invoice;

class ExpenseDuplicateDocumentService
{
    public function normalizeDocumentNumber(?string $documentNumber): ?string
    {
        if (! filled($documentNumber))
        {
            return null;
        }

        $normalized = trim((string) $documentNumber);

        return $normalized === '' ? null : $normalized;
    }

    public function findDuplicate(
        int $teamId,
        int $enterpriseId,
        ?string $documentNumber,
        string $operation = 'buy',
        ?int $ignoreInvoiceId = null,
    ): ?Invoice {
        $normalizedNumber = $this->normalizeDocumentNumber($documentNumber);

        if ($normalizedNumber === null)
        {
            return null;
        }

        return Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('enterprise_id', $enterpriseId)
            ->where('operation', $operation)
            ->whereRaw('LOWER(number) = ?', [mb_strtolower($normalizedNumber)])
            ->when($ignoreInvoiceId !== null, fn ($query) => $query->whereKeyNot($ignoreInvoiceId))
            ->first();
    }

    public function isDuplicate(
        int $teamId,
        int $enterpriseId,
        ?string $documentNumber,
        string $operation = 'buy',
        ?int $ignoreInvoiceId = null,
    ): bool {
        return $this->findDuplicate($teamId, $enterpriseId, $documentNumber, $operation, $ignoreInvoiceId) instanceof Invoice;
    }
}
