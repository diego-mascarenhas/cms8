<?php

namespace App\Services;

use App\Support\AiTasks;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

use function Laravel\Ai\agent;

class CommercialDocumentInterpreter
{
    public function __construct(private DocumentAiOcrService $ocr) {}

    /**
     * @return array{kind: string, supplier_name: ?string, currency: ?string, lines: list<array{code: string, name: string, cost: ?float, quantity: ?float, unit: ?string, barcode: ?string, oem: ?string}>}
     */
    public function interpret(string $path, string $originalName, ?int $teamId = null): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $text = in_array($extension, ['xlsx', 'csv', 'txt'], true)
            ? ($extension === 'xlsx' ? $this->spreadsheetText($path) : (string) file_get_contents($path))
            : (string) $this->ocr->extractTextFromLocalFile($path, $teamId);

        $text = trim($text);
        if ($text === '')
        {
            throw new RuntimeException(__('The file could not be read.'));
        }

        $prompt = $this->prompt(Str::limit($text, 120000, ''));
        $documentAgent = agent(
            instructions: 'You extract commercial lines from supplier lists and ship requisitions. Return only JSON.',
            messages: [],
            tools: [],
        );
        $response = AiTasks::prompt($documentAgent, $prompt, 120, 'shop');

        return $this->documentFromModelText((string) ($response->text ?? ''));
    }

    public function spreadsheetText(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true)
        {
            throw new RuntimeException(__('The spreadsheet could not be read.'));
        }

        $shared = [];
        $sharedXml = $this->officeXml($zip->getFromName('xl/sharedStrings.xml'));
        if ($sharedXml !== null)
        {
            foreach ($sharedXml->si as $item)
            {
                $text = '';
                foreach ($item->xpath('.//t') ?: [] as $part)
                {
                    $text .= (string) $part;
                }
                $shared[] = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
            }
        }

        $lines = [];
        for ($sheet = 1; $sheet <= 3; $sheet++)
        {
            $xml = $this->officeXml($zip->getFromName('xl/worksheets/sheet'.$sheet.'.xml'));
            if ($xml === null)
            {
                continue;
            }

            foreach ($xml->sheetData->row as $row)
            {
                $cells = [];
                foreach ($row->c as $cell)
                {
                    $type = (string) $cell['t'];
                    $value = (string) $cell->v;
                    if ($type === 's' && ctype_digit($value))
                    {
                        $value = $shared[(int) $value] ?? '';
                    }
                    $inline = trim((string) ($cell->is->t ?? ''));
                    if ($inline !== '')
                    {
                        $value = $inline;
                    }
                    if (trim($value) !== '')
                    {
                        $cells[] = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
                    }
                }

                if ($cells !== [])
                {
                    $lines[] = implode("\t", $cells);
                }

                if (count($lines) >= 5000)
                {
                    break 2;
                }
            }
        }

        $zip->close();

        return implode("\n", $lines);
    }

    private function officeXml(string|false $contents): ?\SimpleXMLElement
    {
        if (! is_string($contents) || $contents === '')
        {
            return null;
        }

        $contents = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $contents) ?? $contents;
        $xml = simplexml_load_string($contents);

        return $xml === false ? null : $xml;
    }

    /**
     * @return array{kind: string, supplier_name: ?string, currency: ?string, lines: list<array{code: string, name: string, cost: ?float, quantity: ?float, unit: ?string, barcode: ?string, oem: ?string}>}
     */
    public function documentFromModelText(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?? $text;
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start)
        {
            throw new RuntimeException(__('The file could not be interpreted.'));
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($decoded))
        {
            throw new RuntimeException(__('The file could not be interpreted.'));
        }

        $lines = [];
        foreach (array_slice((array) ($decoded['lines'] ?? []), 0, 500) as $line)
        {
            if (! is_array($line))
            {
                continue;
            }

            $name = trim((string) ($line['name'] ?? ''));
            if ($name === '')
            {
                continue;
            }

            $lines[] = [
                'code' => strtoupper(trim((string) ($line['code'] ?? ''))),
                'name' => $name,
                'cost' => $this->number($line['cost'] ?? null),
                'quantity' => $this->number($line['quantity'] ?? null),
                'unit' => $this->nullableString($line['unit'] ?? null, 32),
                'barcode' => $this->nullableString($line['barcode'] ?? null, 64),
                'oem' => $this->nullableString($line['oem'] ?? null, 80),
            ];
        }

        $kind = ($decoded['kind'] ?? '') === 'requisition' ? 'requisition' : 'price_list';
        if ($lines !== [] && ! collect($lines)->contains(fn (array $line): bool => $line['cost'] !== null))
        {
            $kind = 'requisition';
        }

        $currency = strtoupper(trim((string) ($decoded['currency'] ?? '')));

        return [
            'kind' => $kind,
            'supplier_name' => $this->nullableString($decoded['supplier_name'] ?? null, 75),
            'currency' => in_array($currency, ['ARS', 'USD', 'EUR'], true) ? $currency : null,
            'lines' => $lines,
        ];
    }

    private function prompt(string $text): string
    {
        return <<<PROMPT
Extract the commercial lines from this document. It is either a supplier price list or a ship requisition.

Price list examples: Maxikol product lists, "LISTA REVENTA", LBP price sheets, and supplier quotations. Each product has a name and a unit cost.
Requisition examples: "STORES REQUISITION FORM", RFQ, and galley or bond store sheets. Each item has an ISSA/IMPA code, a description, and a requested quantity. Those rows usually have no unit cost.

Return only JSON with this shape:
{"kind":"price_list","supplier_name":null,"currency":"ARS","lines":[{"code":"","name":"","cost":12900,"quantity":3,"unit":"PC","barcode":null,"oem":null}]}

Rules:
- kind is "price_list" when rows have a unit cost, otherwise "requisition".
- supplier_name is the company that issued the list, or null.
- currency is ARS, USD, or EUR, or null.
- cost is a JSON number. Use a dot as the decimal separator and no thousands separator. 12.900,00 is 12900. If there is no unit cost, cost is null.
- quantity is the amount requested. When there are columns for immediate use and for stock, add them. Ignore ROB. If there is no quantity, quantity is null.
- code is the supplier code or ISSA/IMPA code. Keep internal single spaces. Do not invent codes.
- Skip titles, page numbers, totals, and empty rows. Do not invent products.
- Return at most 500 lines.

Document:
{$text}
PROMPT;
    }

    private function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value))
        {
            return round((float) $value, 2);
        }

        $raw = trim((string) $value);
        if ($raw === '')
        {
            return null;
        }

        $raw = preg_replace('/[^\d,.\-]/', '', $raw) ?? '';
        if ($raw === '' || $raw === '-')
        {
            return null;
        }

        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');
        if ($lastComma !== false && $lastDot !== false)
        {
            if ($lastComma > $lastDot)
            {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else
            {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($lastComma !== false)
        {
            $raw = str_replace(',', '.', $raw);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $raw) === 1)
        {
            $raw = str_replace('.', '', $raw);
        }

        if (! is_numeric($raw))
        {
            return null;
        }

        $number = round((float) $raw, 2);

        return $number < 0 ? null : $number;
    }

    private function nullableString(mixed $value, int $limit): ?string
    {
        $text = trim((string) $value);
        if ($text === '')
        {
            return null;
        }

        return Str::limit($text, $limit, '');
    }
}
