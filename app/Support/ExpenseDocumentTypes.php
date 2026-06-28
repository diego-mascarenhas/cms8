<?php

namespace App\Support;

class ExpenseDocumentTypes
{
    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'invoice' => 'Factura',
        'receipt' => 'Ticket/Recibo',
        'tax' => 'Impuesto',
        'depreciation' => 'Amortización',
        'dividend' => 'Dividendo',
        'payroll' => 'Nómina',
        'loan' => 'Préstamo',
    ];

    /**
     * @var list<string>
     */
    public const DISABLED = [
        'depreciation',
        'dividend',
        'payroll',
        'loan',
    ];

    /**
     * @return list<string>
     */
    public static function enabledKeys(): array
    {
        return array_values(array_diff(array_keys(self::LABELS), self::DISABLED));
    }
}
