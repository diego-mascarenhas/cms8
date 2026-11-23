<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class CommercialDocumentInterpreter
{
    public function __construct(
        private DocumentAiOcrService $ocr,
        private DocumentOcrService $localText = new DocumentOcrService,
    ) {}

    /**
     * @return array{kind: string, supplier_name: ?string, currency: ?string, lines: list<array{code: string, name: string, cost: ?float, quantity: ?float, unit: ?string, barcode: ?string, oem: ?string}>}
     */
    public function interpret(string $path, string $originalName, ?int $teamId = null): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true))
        {
            return $this->interpretImage($path, $teamId);
        }

        $text = trim($this->sourceText($path, $originalName, $teamId));
        $parsed = $text !== ''
            ? ($this->priceListFromText($text, $originalName) ?? $this->quotationFromText($text, $originalName))
            : null;
        if ($parsed !== null)
        {
            return $parsed;
        }

        if ($extension === 'pdf')
        {
            return $this->interpretAttachment($path, $teamId);
        }

        if ($text === '')
        {
            throw new RuntimeException(__('The file could not be read.'));
        }

        $json = $this->ocr->complete(
            'You extract commercial lines from supplier lists and ship requisitions. Return only JSON.',
            $this->promptFor(Str::limit($text, 120000, ''), $teamId),
            $teamId,
        );

        return $this->documentFromModelText($json);
    }

    /**
     * Photos go to the vision OCR model. DeepSeek only sees text and fails on these images.
     *
     * @return array{kind: string, supplier_name: ?string, currency: ?string, lines: list<array{code: string, name: string, cost: ?float, quantity: ?float, unit: ?string, barcode: ?string, oem: ?string}>}
     */
    private function interpretImage(string $path, ?int $teamId): array
    {
        return $this->interpretAttachment($path, $teamId);
    }

    /**
     * PDFs and photos use the invoice vision model. DeepSeek only sees text.
     *
     * @return array{kind: string, supplier_name: ?string, currency: ?string, lines: list<array{code: string, name: string, cost: ?float, quantity: ?float, unit: ?string, barcode: ?string, oem: ?string}>}
     */
    private function interpretAttachment(string $path, ?int $teamId): array
    {
        $prompt = $this->resolveSupplierListPrompt($teamId)
            ."\n\nThe price list is the attached file. Read the products and prices from that file.";
        $text = $this->ocr->completeWithFile(
            $path,
            'You read supplier price lists. Return only JSON.',
            $prompt,
            $teamId,
        );

        return $this->documentFromModelText($text);
    }

    public function sourceText(string $path, string $originalName, ?int $teamId = null): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === 'xlsx')
        {
            return $this->spreadsheetText($path);
        }

        if (in_array($extension, ['csv', 'txt'], true))
        {
            return (string) file_get_contents($path);
        }

        $local = trim((string) $this->localText->extractTextFromLocalFile($path));
        if ($local !== '')
        {
            return $local;
        }

        return trim((string) $this->ocr->extractTextFromLocalFile($path, $teamId));
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
        foreach ($this->spreadsheetSheets($zip) as $sheet)
        {
            $xml = $this->officeXml($zip->getFromName($sheet['path']));
            if ($xml === null)
            {
                continue;
            }

            if ($sheet['name'] !== '')
            {
                $lines[] = '# '.$sheet['name'];
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

    /**
     * @return list<array{name: string, path: string}>
     */
    private function spreadsheetSheets(ZipArchive $zip): array
    {
        $workbook = $this->plainXml($zip->getFromName('xl/workbook.xml'));
        $rels = $this->plainXml($zip->getFromName('xl/_rels/workbook.xml.rels'));
        if ($workbook === null || $rels === null)
        {
            return [
                ['name' => '', 'path' => 'xl/worksheets/sheet1.xml'],
                ['name' => '', 'path' => 'xl/worksheets/sheet2.xml'],
                ['name' => '', 'path' => 'xl/worksheets/sheet3.xml'],
            ];
        }

        $targets = [];
        $package = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $relationships = $rels->children($package)->Relationship;
        if (count($relationships) === 0)
        {
            $relationships = $rels->Relationship;
        }

        foreach ($relationships as $relationship)
        {
            $attributes = $relationship->attributes();
            $id = (string) $attributes['Id'];
            $target = (string) $attributes['Target'];
            if ($id === '' || $target === '')
            {
                continue;
            }

            $targets[$id] = str_starts_with($target, '/')
                ? ltrim($target, '/')
                : (str_starts_with($target, 'xl/') ? $target : 'xl/'.ltrim($target, '/'));
        }

        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $relation = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $sheets = [];
        foreach ($workbook->children($main)->sheets->sheet as $sheet)
        {
            $attributes = $sheet->attributes();
            $state = strtolower((string) $attributes['state']);
            $name = trim((string) $attributes['name']);
            if ($state === 'hidden' || $state === 'veryhidden' || strcasecmp($name, 'INTRO') === 0)
            {
                continue;
            }

            $id = (string) $sheet->attributes($relation)['id'];
            $path = $targets[$id] ?? '';
            if ($path === '')
            {
                continue;
            }

            $sheets[] = [
                'name' => $name,
                'path' => $path,
            ];
        }

        return $sheets !== [] ? $sheets : [
            ['name' => '', 'path' => 'xl/worksheets/sheet1.xml'],
        ];
    }

    private function plainXml(string|false $contents): ?\SimpleXMLElement
    {
        if (! is_string($contents) || $contents === '')
        {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml === false ? null : $xml;
    }

    private function officeXml(string|false $contents): ?\SimpleXMLElement
    {
        if (! is_string($contents) || $contents === '')
        {
            return null;
        }

        // Excel declares compatibility prefixes (mc, x14ac, xr) and then uses
        // them on the worksheet. Drop the declarations, the prefixed attributes
        // (mc:Ignorable), and prefixes on tags so local names stay readable.
        $contents = preg_replace('/\sxmlns(:[\w.-]+)?="[^"]*"/', '', $contents) ?? $contents;
        $contents = preg_replace('/\s[\w.-]+:[\w.-]+="[^"]*"/', '', $contents) ?? $contents;
        $contents = preg_replace('/(<\/?)[\w.-]+:/', '$1', $contents) ?? $contents;

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

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

    /**
     * @return array{kind: string, supplier_name: ?string, currency: ?string, lines: list<array{code: string, name: string, cost: ?float, quantity: ?float, unit: ?string, barcode: ?string, oem: ?string}>}|null
     */
    public function priceListFromText(string $text, string $originalName = ''): ?array
    {
        $rows = [];
        $preface = [];
        $pending = [];
        foreach (preg_split('/\r\n|\n|\r/', $text) ?: [] as $rawLine)
        {
            $line = trim($rawLine);
            if ($line === '')
            {
                continue;
            }

            if ($pending !== [] && preg_match('/^\d{5,14}\s/', $line) !== 1)
            {
                $pending[] = $line;
                $row = $this->pricedRow(implode(' ', $pending));
                if ($row !== null)
                {
                    $rows[] = $row;
                    $pending = [];
                    if (count($rows) >= 2000)
                    {
                        break;
                    }
                }

                continue;
            }

            if ($pending !== [])
            {
                $preface[] = implode(' ', $pending);
                $pending = [];
            }

            $row = $this->pricedRow($line);
            if ($row !== null)
            {
                $rows[] = $row;
                if (count($rows) >= 2000)
                {
                    break;
                }

                continue;
            }

            if (preg_match('/^\d{5,14}\s/', $line) === 1)
            {
                $pending = [$line];

                continue;
            }

            if ($rows === [] && $pending === [] && preg_match('/\b(COD(?:\s*REF)?|PRODUCTO|PV\s*\d)\b/i', $line) !== 1)
            {
                $preface[] = $line;
            }
        }

        if ($pending !== [])
        {
            $row = $this->pricedRow(implode(' ', $pending));
            if ($row !== null && count($rows) < 2000)
            {
                $rows[] = $row;
            }
        }

        if (count($rows) < 3)
        {
            return null;
        }

        return [
            'kind' => 'price_list',
            'supplier_name' => $this->supplierFromPreface($preface, $originalName),
            'currency' => null,
            'lines' => $rows,
        ];
    }

    /**
     * Ship-chandler quotations: item, description, code, quantity, unit, unit price, discount and amount.
     *
     * @return array{kind: string, supplier_name: ?string, currency: ?string, lines: list<array{code: string, name: string, cost: ?float, quantity: ?float, unit: ?string, barcode: ?string, oem: null}>}|null
     */
    public function quotationFromText(string $text, string $originalName = ''): ?array
    {
        $pending = null;
        $rawRows = [];
        foreach (preg_split('/\r\n|\n|\r/', $text) ?: [] as $rawLine)
        {
            $line = trim($rawLine);
            if ($line === '' || preg_match('/^Page\s+\d+\s+of\s+\d+$/i', $line) === 1)
            {
                continue;
            }

            if (preg_match('/^\d{1,4}[A-Za-z].*\t\d{4,14}\t/', $line) === 1)
            {
                if ($pending !== null)
                {
                    $rawRows[] = $pending;
                }
                $pending = $line;

                continue;
            }

            if ($pending !== null && ! str_contains($line, "\t") && preg_match('/^(Subtotal|Grand Total|Total)\b/i', $line) !== 1)
            {
                $columns = explode("\t", $pending, 2);
                $columns[0] = trim($columns[0].' '.$line);
                $pending = implode("\t", $columns);
            }
        }

        if ($pending !== null)
        {
            $rawRows[] = $pending;
        }

        $lines = [];
        foreach ($rawRows as $row)
        {
            $parsed = $this->quotationRow($row);
            if ($parsed !== null && count($lines) < 2000)
            {
                $lines[] = $parsed;
            }
        }

        if (count($lines) < 3)
        {
            return null;
        }

        $currency = null;
        if (preg_match('/\b(USD|EUR|ARS)\b/', $text, $match) === 1)
        {
            $currency = $match[1];
        }

        $priced = false;
        foreach ($lines as $line)
        {
            if ($line['cost'] !== null)
            {
                $priced = true;

                break;
            }
        }

        return [
            'kind' => $priced ? 'price_list' : 'requisition',
            'supplier_name' => $this->supplierFromQuotation($text, $originalName),
            'currency' => $currency,
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<string>  $lines
     */
    private function supplierFromPreface(array $lines, string $originalName): ?string
    {
        foreach ($lines as $line)
        {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? $line);
            if (preg_match('/^([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ0-9&.\'\-]{2,})(?:\s|$)/u', $line, $match) === 1)
            {
                return $this->nullableString($match[1], 75);
            }
        }

        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $base = preg_replace('/^\d{4}[-_.]\d{2}[-_.]\d{2}[-_.]/', '', $base) ?? $base;
        $base = preg_replace('/[-_.]+(listado|lista|productos|precios|price).*/i', '', $base) ?? $base;
        $base = trim(str_replace(['-', '_', '.'], ' ', $base));

        return $this->nullableString($base, 75);
    }

    private function supplierFromQuotation(string $text, string $originalName): ?string
    {
        $haystack = $originalName.' '.$text;
        if (preg_match('/seven[\s_.-]*seas/i', $haystack) === 1)
        {
            $name = 'Seven Seas Ship Suppliers';
            if (preg_match('/argentina/i', $haystack) === 1)
            {
                $name .= ' Argentina';
            }

            return $name;
        }

        return $this->supplierFromPreface([], $originalName);
    }

    /**
     * @return array{code: string, name: string, cost: ?float, quantity: ?float, unit: ?string, barcode: null, oem: null}|null
     */
    private function quotationRow(string $line): ?array
    {
        $parts = array_values(array_filter(array_map('trim', explode("\t", $line)), fn (string $part): bool => $part !== ''));
        if (count($parts) < 4)
        {
            return null;
        }

        $name = trim((string) preg_replace('/^\d{1,4}/', '', $parts[0]));
        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        $code = strtoupper($parts[1]);
        if ($name === '' || preg_match('/^\d{4,14}$/', $code) !== 1)
        {
            return null;
        }

        $unitIndex = null;
        $unit = null;
        foreach ($parts as $index => $part)
        {
            if (preg_match('/^(KG|KGS|PC|PCS|LT|LTR|ML|GR|BOX|CTN|TIN|BTL|SET|EA|DOZ)$/i', $part) === 1)
            {
                $unitIndex = $index;
                $unit = strtoupper($part === 'KGS' ? 'KG' : $part);

                break;
            }
        }

        $requested = $unitIndex !== null ? $this->number($parts[$unitIndex - 1] ?? null) : null;
        $supplied = $unitIndex !== null ? $this->number($parts[$unitIndex + 1] ?? null) : null;
        $price = $unitIndex !== null ? $this->number($parts[$unitIndex + 2] ?? null) : null;
        $discount = null;
        if ($unitIndex !== null && preg_match('/^([\d.]+)\s*%$/', $parts[$unitIndex + 3] ?? '', $match) === 1)
        {
            $discount = (float) $match[1];
        }
        $amount = $unitIndex !== null ? $this->number($parts[$unitIndex + 4] ?? null) : null;
        $quantity = $supplied ?? $requested;
        $cost = null;
        if ($amount !== null && $quantity !== null && $quantity > 0)
        {
            $cost = round($amount / $quantity, 2);
        } elseif ($price !== null)
        {
            $cost = $discount !== null && $discount > 0 && $discount < 100
                ? round($price * (1 - ($discount / 100)), 2)
                : $price;
        }

        return [
            'code' => $code,
            'name' => $name,
            'cost' => $cost,
            'quantity' => $quantity,
            'unit' => $unit,
            'barcode' => null,
            'oem' => null,
        ];
    }

    /**
     * @return array{code: string, name: string, cost: ?float, quantity: null, unit: null, barcode: ?string, oem: null}|null
     */
    private function pricedRow(string $line): ?array
    {
        $line = trim(preg_replace('/[ \t]+/', ' ', $line) ?? $line);
        $price = '(?:\d{1,3}(?:\.\d{3})+,\d{2}|\d{1,3}(?:,\d{3})+\.\d{2}|\d+[.,]\d{2})';
        if (preg_match('/^(\d{5,14})\s+(.+?)\s+('.$price.')(?:\s+('.$price.'))?$/', $line, $match) !== 1)
        {
            return null;
        }

        $name = trim($match[2]);
        $cost = $this->number($match[3]);
        if ($name === '' || $cost === null)
        {
            return null;
        }

        $code = $match[1];

        return [
            'code' => $code,
            'name' => $name,
            'cost' => $cost,
            'quantity' => null,
            'unit' => null,
            'barcode' => strlen($code) >= 8 ? $code : null,
            'oem' => null,
        ];
    }

    public function promptFor(string $text, ?int $teamId = null): string
    {
        return $this->resolveSupplierListPrompt($teamId)."\n\nDocument:\n{$text}";
    }

    public function resolveSupplierListPrompt(?int $teamId): string
    {
        if ($teamId === null)
        {
            return $this->defaultSupplierListPrompt();
        }

        $prompt = Prompt::forTeam($teamId)
            ->forModule('shop')
            ->where('section_key', 'supplier_price_list')
            ->active()
            ->first();
        $instruction = trim((string) ($prompt?->prompt_instruction ?? ''));
        if ($instruction === '')
        {
            return $this->defaultSupplierListPrompt();
        }

        $withLists = $this->withCurrentSupplierLists($instruction);
        if ($withLists !== $instruction && $prompt !== null)
        {
            $prompt->prompt_instruction = $withLists;
            $prompt->save();
        }

        return $withLists;
    }

    public function withCurrentSupplierLists(string $instruction): string
    {
        if (str_contains($instruction, 'MarJuanC') || ! str_contains($instruction, 'LISTA REVENTA'))
        {
            return $instruction;
        }

        $formats = $this->addedSupplierListFormats();
        $marker = 'Return only JSON';
        if (str_contains($instruction, $marker))
        {
            return str_replace($marker, $formats."\n\n".$marker, $instruction);
        }

        return rtrim($instruction)."\n\n".$formats;
    }

    public function addedSupplierListFormats(): string
    {
        return <<<'PROMPT'
- MarJuanC alimentos lists, dated like 13-11-2026, from Grito de Asencio. A row is a short code, a product name, a parenthetical such as (21.00), a discount quantity, a unit (Uni or Kg), Prec. Lista, a discount percent, and Precio bonif. cost is Precio bonif. 17,753.37 is 17753.37. The parentheses are IVA, not a pack count and not quantity. Cant p/ desc is not an order quantity. A note such as "+1 bolsa bonif." is not a price. Category bands such as ACEITES, AZUCARES, BAKERY and CREMAS y MANTECA are not products. supplier_name is MarJuanC. currency is ARS. Example: ACE120 ACEITE DE GIRASOL DON HUGO X 5 LTS (21.00), Precio bonif. 17,753.37, so cost is 17753.37.
- Del Campo Distribuidora, "LISTA DE PRECIOS COMPLETA". Columns are PRODUCTO, MARCA, TIPO ENVASE, CONTENIDO and PRECIO FINAL. name is the product plus the brand when the brand is not "CONSULTAR MARCA". cost is PRECIO FINAL. $8.508 means 8508. CONTENIDO such as 850 GRS or 3,1 KILOS stays in the name. ENCARGADo or a missing price has cost null. Section titles such as CONSERVAS DE FRUTA and SALSAS Y ADEREZOS are not products. Skip phones, gluten and kosher marks, and the stock disclaimer. supplier_name is Del Campo Distribuidora. currency is ARS.
- La Reina Mayorista, also shown as Precios Mayorista or Mayorista Almacén. One sheet per category: ALMACEN, QUESOS, FIAMBRES, ENCURTIDOS, FRUTAS SECAS, CONDIMENTOS, CONGELADOS and PAPELERA. Skip the INTRO sheet. Columns are Producto, Consumidor Final, Consumidor Final en Efectivo, Precios Comerciantes and Precios Comerciantes por cantidad. cost is Precios Comerciantes, the third price. Do not use the consumer prices or the quantity price. 23,300.50 and 23300.5 are 23300.5. Rows marked NUEVO or OFERTA are still products. Category labels such as JAMON CRUDO, BARRAS and HAMBURGUESAS are not products. Skip the Muñiz banner, the Saturday delivery note, the phone, the mail and Instagram. supplier_name is La Reina Mayorista. currency is ARS. A screenshot of that same grid is the same list: ignore the browser and the desktop around it.
PROMPT;
    }

    public function ensureSupplierListPrompt(Team $team): Prompt
    {
        $module = Module::query()->firstOrCreate(
            ['key' => 'shop'],
            [
                'name' => 'Shop',
                'icon' => 'ti ti-building-store',
                'description' => 'Shop',
                'is_core' => false,
                'group' => 'work',
                'order' => 0,
                'status' => 1,
            ],
        );

        return Prompt::withoutGlobalScope('team')->firstOrCreate(
            [
                'team_id' => $team->id,
                'module_id' => $module->id,
                'section_key' => 'supplier_price_list',
            ],
            [
                'section_label' => 'Proveedores: lectura de listas de precios',
                'prompt_instruction' => $this->defaultSupplierListPrompt(),
                'helper_text' => 'Prompt que lee listas de precios y pedidos de proveedores. La respuesta es JSON con kind, supplier_name, currency y lines.',
                'order' => 4,
                'is_active' => true,
            ],
        );
    }

    public function defaultSupplierListPrompt(): string
    {
        return <<<'PROMPT'
Extract the commercial lines from this document. It is either a supplier price list or a ship requisition.

Price lists from this operation:
- LISTA REVENTA: a date, category headers, then a numeric code, an abbreviated product name, a pack count in parentheses, and a price that starts with $. The pack count is not an order quantity. cost is the unit price. Example: 1101287 ALF.AGUILA M.TORTAx69g.DARK (21) $1229,98
- LBP and palta.com.ar sheets: price per kilogram, Argentine format such as 1.044,00. unit is KG.
- Photo flyers such as Don Silvio / La Granja Don Silvio: columns VACUNA, POLLO, CERDO, EMBUTIDOS and ACHURAS. Each row is a cut and a price per kilogram. $ 13.890 means 13890. A cell that says consultar has cost null. supplier_name is the brand printed on the flyer. currency is ARS. unit is KG. Skip slogans, phones, delivery banners and the minimum order.
- Wholesale magazines and Lista de Precios Mayorista: read only product rows. Skip ads, page numbers and IVA notes.
- Maxikol product lists and other supplier quotations. Each product has a name and a unit cost.
PROMPT
            ."\n"
            .$this->addedSupplierListFormats()
            ."\n\n"
            .<<<'PROMPT'
Requisition examples: "STORES REQUISITION FORM", RFQ, and galley or bond store sheets. Each item has an ISSA/IMPA code, a description, and a requested quantity. Those rows usually have no unit cost.
Quotation examples: Seven Seas qt sheets. A row has the item number, description, supplier code, requested quantity, unit, supplied quantity, unit price, discount percent and line amount. cost is the net unit price, the line amount divided by the supplied quantity. Rows marked NA have no cost. currency is the code printed beside the grand total.

Return only JSON with this shape:
{"kind":"price_list","supplier_name":null,"currency":"ARS","lines":[{"code":"","name":"","cost":12900,"quantity":3,"unit":"PC","barcode":null,"oem":null}]}

Rules:
- kind is "price_list" when rows have a unit cost, otherwise "requisition".
- supplier_name is the company that issued the list, or null.
- currency is ARS, USD, or EUR, or null.
- cost is a JSON number. Use a dot as the decimal separator and no thousands separator. 12.900,00 and 13.890 are 12900 and 13890. 1229,98 is 1229.98. 17,753.37 and 23300.5 are 17753.37 and 23300.5. If there is no unit cost, cost is null.
- quantity is the amount requested. When there are columns for immediate use and for stock, add them. Ignore ROB. A pack count in parentheses is not quantity. If there is no quantity, quantity is null.
- code is the supplier code or ISSA/IMPA code. Keep internal single spaces. Do not invent codes.
- Skip titles, page numbers, totals, ads, phones and empty rows. Do not invent products.
- Return at most 500 lines.
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
