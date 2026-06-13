<?php

namespace App\Services\Fiscal;

use App\Contracts\Fiscal\FiscalExportAdapter;
use App\Models\FiscalExport;
use App\Models\Invoice;

class NullFiscalExportAdapter implements FiscalExportAdapter
{
    public const PLATFORM = 'none';

    public function platform(): string
    {
        return self::PLATFORM;
    }

    public function supports(Invoice $invoice): bool
    {
        return false;
    }

    public function export(Invoice $invoice): FiscalExportResult
    {
        return FiscalExportResult::skipped('No fiscal platform configured for this invoice.');
    }

    public function voidOrRectify(Invoice $invoice, FiscalExport $existing): FiscalExportResult
    {
        return FiscalExportResult::skipped('No fiscal platform configured for this invoice.');
    }
}
