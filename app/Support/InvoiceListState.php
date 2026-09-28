<?php

namespace App\Support;

use App\Services\Finance\InvoiceSummaryService;

class InvoiceListState
{
    /**
     * Query values that can restore the invoice list after leaving it.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function fromArray(array $input): array
    {
        $summary = app(InvoiceSummaryService::class)->resolveListFilter(
            is_string($input['summary_filter'] ?? null) ? $input['summary_filter'] : null,
        );

        $operation = $input['operation_filter'] ?? null;
        $operation = is_string($operation) && in_array($operation, ['buy', 'sell'], true)
            ? $operation
            : null;

        $search = trim((string) ($input['search'] ?? ''));
        if (mb_strlen($search) > 200)
        {
            $search = mb_substr($search, 0, 200);
        }

        $state = [];

        if ($summary !== InvoiceSummaryService::DEFAULT_LIST_FILTER)
        {
            $state['summary_filter'] = $summary;
        }

        if ($operation !== null)
        {
            $state['operation_filter'] = $operation;
        }

        if ($search !== '')
        {
            $state['search'] = $search;
        }

        return $state;
    }
}
