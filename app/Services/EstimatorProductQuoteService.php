<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Prompt;
use App\Models\Team;
use App\Support\AiTasks;
use RuntimeException;

use function Laravel\Ai\agent;

class EstimatorProductQuoteService
{
    /**
     * Interpret a pasted product request into a priced quote. Nothing is stored.
     *
     * @return array<string, mixed>
     */
    public function quote(string $brief, Team $team): array
    {
        $brief = trim($brief);
        if ($brief === '')
        {
            throw new RuntimeException(__('The budget text is required.'));
        }

        return $this->present($team, $this->interpret($brief, $team), $brief);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public function present(Team $team, array $spec, string $brief = ''): array
    {
        $summary = $this->plainText($spec['summary'] ?? '');
        $title = $this->plainText($spec['title'] ?? '');
        if ($title === '' && $summary !== '')
        {
            $title = trim(strtok($summary, "\n") ?: $summary);
        }
        if ($title === '')
        {
            $title = 'Presupuesto';
        }

        $lines = [];
        $totalsByCurrency = [];
        foreach ($this->linesFromSpec($spec) as $line)
        {
            $line['code'] = $this->codeWrittenInBrief($line['code'], $brief);
            $product = $this->matchProduct((int) $team->id, $line);
            $quantity = $line['quantity'];
            $currency = $product?->currency?->code ? (string) $product->currency->code : '';
            $unitPrice = $product ? $product->currentSellingPrice() : null;
            $lineTotal = $unitPrice !== null ? round($quantity * $unitPrice, 2) : null;
            if ($lineTotal !== null && $currency !== '')
            {
                $totalsByCurrency[$currency] = round(($totalsByCurrency[$currency] ?? 0) + $lineTotal, 2);
            }

            $lines[] = [
                'name' => $product?->name ?? $line['name'],
                'code' => $product?->code ?? $line['code'],
                'quantity' => $quantity,
                'unit' => $line['unit'],
                'detail' => $line['detail'],
                'matched' => $product !== null,
                'product_id' => $product?->id,
                'currency_id' => $product?->currency_id,
                'unit_price_amount' => $unitPrice,
                'line_total_amount' => $lineTotal,
                'unit_price' => $unitPrice !== null && $currency !== '' ? $this->formatMoney($unitPrice, $currency) : null,
                'line_total' => $lineTotal !== null && $currency !== '' ? $this->formatMoney($lineTotal, $currency) : null,
            ];
        }

        if ($lines === [])
        {
            throw new RuntimeException(__('Could not generate the estimate. Please try again.'));
        }

        $total = null;
        if (count($totalsByCurrency) === 1)
        {
            $currency = (string) array_key_first($totalsByCurrency);
            $total = $this->formatMoney($totalsByCurrency[$currency], $currency);
        }

        return [
            'name' => mb_substr($title, 0, 120),
            'summary' => $summary,
            'lines' => $lines,
            'totals' => [
                'total' => $total,
            ],
        ];
    }

    public function promptFor(Team $team): string
    {
        $stored = Prompt::findByRoutingKey('shop:product_quote', (int) $team->id);
        $instruction = trim((string) ($stored?->prompt_instruction ?? ''));
        if ($instruction !== '')
        {
            return $instruction;
        }

        return $this->defaultPrompt();
    }

    public function defaultPrompt(): string
    {
        return <<<'PROMPT'
You turn a pasted product request into a clear sales quote. The text may be a message, a list, or a messy note.

Respond with ONLY a valid JSON object. No markdown and no explanation.
Use exactly these keys:
- "title": short quote title in Spanish.
- "summary": two or three short sentences in Spanish that say which products are being quoted and any quantity or variant that matters. Plain language for the buyer. Do not mention hours, tokens, labor, project phases, resources, or commercial mentoring.
- "lines": array of products.

Each line uses exactly these keys:
- "name": the product name exactly as written in the request. Do not translate it and do not replace it with a generic description.
- "code": a code written in the request. Empty string if none was written. Never invent a code. If the request only gives a product name, leave this empty.
- "quantity": JSON number greater than 0. Use 1 when the request does not say how many.
- "unit": short Spanish unit such as "u", "kg", "l", or "caja".
- "detail": one short Spanish sentence only when the request left the size, variant, or presentation ambiguous. Empty string when the name is already specific.

Rules:
- Split a combined request into one line per product.
- Do not add products that were not asked for.
- Do not include prices. The catalog price is applied later.
- Return at most 80 lines.
PROMPT;
    }

    /**
     * @return array{title: string, summary: string, lines: list<array<string, mixed>>}
     */
    private function interpret(string $brief, Team $team): array
    {
        $instructions = $this->promptFor($team);
        $userMessage = $instructions."\n\n---\n\nPedido:\n\n".$brief;

        try
        {
            $response = AiTasks::prompt(
                agent(instructions: $instructions, messages: [], tools: []),
                $userMessage,
                120,
                'shop',
            );
            $text = (string) ($response->text ?? '');
        } catch (\Throwable $exception)
        {
            throw new RuntimeException(__('Could not generate the estimate. Please try again.'), 0, $exception);
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $match))
        {
            $text = trim($match[1]);
        }

        $decoded = json_decode(trim($text), true);
        if (! is_array($decoded))
        {
            throw new RuntimeException(__('Could not generate the estimate. Please try again.'));
        }

        return [
            'title' => $this->plainText($decoded['title'] ?? ''),
            'summary' => $this->plainText($decoded['summary'] ?? ''),
            'lines' => is_array($decoded['lines'] ?? null) ? $decoded['lines'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<array{name: string, code: string, quantity: float, unit: string, detail: string}>
     */
    private function linesFromSpec(array $spec): array
    {
        $raw = is_array($spec['lines'] ?? null) ? $spec['lines'] : [];
        $lines = [];
        foreach ($raw as $row)
        {
            if (! is_array($row))
            {
                continue;
            }
            $name = $this->plainText($row['name'] ?? '');
            $quantity = is_numeric($row['quantity'] ?? null) ? (float) $row['quantity'] : 0.0;
            if ($name === '' || $quantity <= 0)
            {
                continue;
            }
            $lines[] = [
                'name' => $name,
                'code' => $this->plainText($row['code'] ?? ''),
                'quantity' => $quantity,
                'unit' => $this->plainText($row['unit'] ?? '') ?: 'u',
                'detail' => $this->plainText($row['detail'] ?? ''),
            ];
            if (count($lines) >= 80)
            {
                break;
            }
        }

        return $lines;
    }

    private function plainText(mixed $value): string
    {
        if (is_string($value) || is_numeric($value))
        {
            return trim((string) $value);
        }

        if (! is_array($value))
        {
            return '';
        }

        $parts = [];
        foreach ($value as $item)
        {
            $text = $this->plainText($item);
            if ($text !== '')
            {
                $parts[] = $text;
            }
        }

        return trim(implode(' ', $parts));
    }

    /**
     * @param  array{name: string, code: string}  $line
     */
    private function matchProduct(int $teamId, array $line): ?Product
    {
        $base = Product::query()->where('team_id', $teamId)->with('currency');
        $compact = strtoupper((string) preg_replace('/\s+/', '', $line['code']));
        if ($compact !== '')
        {
            $byCode = (clone $base)->where(function ($query) use ($line, $compact): void
            {
                $query->where('code', $line['code'])
                    ->orWhereRaw("REPLACE(UPPER(code), ' ', '') = ?", [$compact])
                    ->orWhereRaw("REPLACE(UPPER(COALESCE(barcode, '')), ' ', '') = ?", [$compact])
                    ->orWhereRaw("REPLACE(UPPER(COALESCE(oem, '')), ' ', '') = ?", [$compact]);
            })->first();
            if ($byCode)
            {
                return $byCode;
            }
        }

        foreach ($this->nameCandidates($line) as $name)
        {
            $byName = (clone $base)->whereRaw('LOWER(name) = LOWER(?)', [$name])->first();
            if ($byName)
            {
                return $byName;
            }
        }

        return null;
    }

    /**
     * @param  array{name: string, code: string}  $line
     * @return list<string>
     */
    private function nameCandidates(array $line): array
    {
        $names = [];
        foreach ([$line['name'], $line['code']] as $value)
        {
            $value = trim($value);
            if ($value === '')
            {
                continue;
            }
            $key = mb_strtolower($value);
            if (! in_array($key, array_map(mb_strtolower(...), $names), true))
            {
                $names[] = $value;
            }
        }

        return $names;
    }

    private function codeWrittenInBrief(string $code, string $brief): string
    {
        $code = trim($code);
        $brief = trim($brief);
        if ($code === '' || $brief === '')
        {
            return $code;
        }

        $compactCode = strtoupper((string) preg_replace('/\s+/', '', $code));
        $compactBrief = strtoupper((string) preg_replace('/\s+/', '', $brief));
        if ($compactCode === '' || ! str_contains($compactBrief, $compactCode))
        {
            return '';
        }

        return $code;
    }

    private function formatMoney(float|int $amount, string $currencyCode): string
    {
        $formatted = number_format((float) $amount, 2, ',', '.');

        return match (strtoupper($currencyCode))
        {
            'USD' => 'US$ '.$formatted,
            'ARS' => $formatted.' ARS',
            default => $formatted.' €',
        };
    }
}
