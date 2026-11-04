<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Team;

class QuoteDocumentService
{
    public function __construct(private CommercialDocumentInterpreter $documents) {}

    /**
     * @return array{kind: string, supplier_name: ?string, lines: list<array{matched: bool, product_id: ?int, name: string, code: string, quantity: float, unit_price: ?float, currency: ?array{id: int, code: string, symbol: ?string}}>}
     */
    public function quoteLines(string $path, string $originalName, Team $team): array
    {
        $document = $this->documents->interpret($path, $originalName, (int) $team->id);
        $lines = [];

        foreach ($document['lines'] as $line)
        {
            $product = $this->matchProduct((int) $team->id, $line);
            $quantity = $line['quantity'] !== null && $line['quantity'] > 0 ? $line['quantity'] : 1.0;
            $lines[] = [
                'matched' => $product !== null,
                'product_id' => $product?->id,
                'name' => $product?->name ?? $line['name'],
                'code' => $product?->code ?? $line['code'],
                'quantity' => $quantity,
                'unit_price' => $product ? $product->currentSellingPrice() : null,
                'currency' => $product?->currency ? [
                    'id' => (int) $product->currency->id,
                    'code' => (string) $product->currency->code,
                    'symbol' => $product->currency->symbol,
                ] : null,
            ];
        }

        return [
            'kind' => $document['kind'],
            'supplier_name' => $document['supplier_name'],
            'lines' => $lines,
        ];
    }

    /**
     * @param  array{code: string, name: string, oem: ?string}  $line
     */
    private function matchProduct(int $teamId, array $line): ?Product
    {
        $base = Product::query()->where('team_id', $teamId)->with('currency');
        $compact = preg_replace('/\s+/', '', $line['code']) ?? '';
        if ($compact !== '')
        {
            $byCode = (clone $base)->where(function ($query) use ($line, $compact)
            {
                $query->where('code', $line['code'])
                    ->orWhereRaw("REPLACE(UPPER(code), ' ', '') = ?", [$compact])
                    ->orWhereRaw("REPLACE(UPPER(COALESCE(oem, '')), ' ', '') = ?", [$compact]);
            })->first();
            if ($byCode)
            {
                return $byCode;
            }
        }

        return (clone $base)->whereRaw('LOWER(name) = ?', [mb_strtolower($line['name'])])->first();
    }
}
