<?php

namespace App\Contracts\Fiscal;

use App\Models\FiscalExport;
use App\Models\Invoice;
use App\Services\Fiscal\FiscalExportResult;

interface FiscalExportAdapter
{
    /**
     * Platform identifier (e.g. "cuentica", "arca").
     */
    public function platform(): string;

    /**
     * Whether this adapter can export the given invoice.
     */
    public function supports(Invoice $invoice): bool;

    /**
     * Issue/copy the invoice into the fiscal platform.
     */
    public function export(Invoice $invoice): FiscalExportResult;

    /**
     * Issue a rectification (credit note) for an already exported invoice.
     */
    public function voidOrRectify(Invoice $invoice, FiscalExport $existing): FiscalExportResult;
}
