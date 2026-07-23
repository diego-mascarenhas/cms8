<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class CreditNoteNumberAllocator
{
    /**
     * Next Humano credit-note number for a team/serie, e.g. CN-0005-0001.
     * Uses a transaction + lock to keep correlative sequences safe under concurrency.
     */
    public function next(int $teamId, string $seriePrefix, int $pad = 4): string
    {
        $seriePrefix = $this->normalizeSeriePrefix($seriePrefix);
        $pattern = '/^CN-'.preg_quote($seriePrefix, '/').'-(\\d+)$/';

        return DB::transaction(function () use ($teamId, $seriePrefix, $pattern, $pad): string
        {
            $numbers = Invoice::withoutGlobalScopes()
                ->where('team_id', $teamId)
                ->where('type_id', 2)
                ->where('number', 'like', 'CN-'.$seriePrefix.'-%')
                ->lockForUpdate()
                ->pluck('number');

            $max = 0;
            foreach ($numbers as $number)
            {
                if (preg_match($pattern, (string) $number, $matches) !== 1)
                {
                    continue;
                }

                $max = max($max, (int) $matches[1]);
            }

            return sprintf('CN-%s-%s', $seriePrefix, str_pad((string) ($max + 1), $pad, '0', STR_PAD_LEFT));
        });
    }

    /**
     * Derive serie prefix from an invoice/CN number like 0005-0833 or CN-0005-0001 → 0005.
     */
    public function seriePrefixFromInvoiceNumber(?string $number, string $fallback = '0005'): string
    {
        $number = trim((string) $number);
        if ($number !== '' && preg_match('/^CN-(\\d{4})-/', $number, $matches) === 1)
        {
            return $matches[1];
        }

        if ($number !== '' && preg_match('/^(\\d{4})-/', $number, $matches) === 1)
        {
            return $matches[1];
        }

        return $this->normalizeSeriePrefix($fallback);
    }

    public function isHumanoCreditNoteNumber(string $number): bool
    {
        return preg_match('/^CN-\\d{4}-\\d+$/', trim($number)) === 1;
    }

    private function normalizeSeriePrefix(string $seriePrefix): string
    {
        $seriePrefix = trim($seriePrefix);
        if ($seriePrefix === '')
        {
            return '0005';
        }

        if (preg_match('/^(\\d{4})/', $seriePrefix, $matches) === 1)
        {
            return $matches[1];
        }

        return str_pad(preg_replace('/\\D+/', '', $seriePrefix) ?: '5', 4, '0', STR_PAD_LEFT);
    }
}
