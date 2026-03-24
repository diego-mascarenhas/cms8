<?php

namespace App\Services\WhatsApp;

/**
 * Parses the shared "Concepto / Propuesta / Cliente / Importe …" CSV block (commas or semicolons).
 * Caller is responsible for stripping command prefixes (task.store / invoice.store) and BOM handling if needed.
 */
final class WhatsAppConceptoSheetParser
{
    private const REQUIRED_HEADER_KEYS = ['concepto', 'propuesta', 'cliente', 'importe'];

    /**
     * @return array{rows: array<int, array<string, string>>}|null
     */
    public function parse(string $body): ?array
    {
        $normalized = str_replace("\r\n", "\n", trim($body));
        $normalized = str_replace("\r", "\n", $normalized);
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
        $lines = array_values(array_filter(
            explode("\n", $normalized),
            static fn (string $l): bool => trim($l) !== '',
        ));

        if (count($lines) < 2)
        {
            return null;
        }

        for ($headerIndex = 0; $headerIndex < count($lines) - 1; $headerIndex++)
        {
            $headerLine = $lines[$headerIndex];
            $parsedHeader = $this->tryParseHeaderLine($headerLine);
            if ($parsedHeader === null)
            {
                continue;
            }

            [$delimiter, $keys] = $parsedHeader;
            $rows = [];
            for ($i = $headerIndex + 1; $i < count($lines); $i++)
            {
                $values = str_getcsv($lines[$i], $delimiter);
                $valueCount = count($values);
                $keyCount = count($keys);
                if ($valueCount < $keyCount)
                {
                    $values = array_pad($values, $keyCount, '');
                } elseif ($valueCount > $keyCount)
                {
                    $values = array_slice($values, 0, $keyCount);
                }

                $row = [];
                foreach ($keys as $idx => $key)
                {
                    $row[$key] = trim((string) ($values[$idx] ?? ''));
                }
                $rows[] = $row;
            }

            return ['rows' => $rows];
        }

        return null;
    }

    /**
     * @return array{0: string, 1: array<int, string>}|null delimiter and ordered column keys
     */
    private function tryParseHeaderLine(string $line): ?array
    {
        $delimiter = $this->detectDelimiter($line);
        $headerCells = str_getcsv($line, $delimiter);
        $keys = [];
        foreach ($headerCells as $cell)
        {
            $key = $this->headerToKey((string) $cell);
            if ($key === null)
            {
                return null;
            }
            $keys[] = $key;
        }

        foreach (self::REQUIRED_HEADER_KEYS as $required)
        {
            if (! in_array($required, $keys, true))
            {
                return null;
            }
        }

        return [$delimiter, $keys];
    }

    private function detectDelimiter(string $firstLine): string
    {
        $commas = substr_count($firstLine, ',');
        $semis = substr_count($firstLine, ';');

        return $semis > $commas ? ';' : ',';
    }

    private function headerToKey(string $raw): ?string
    {
        $n = $this->normalizeLabel($raw);

        return match (true)
        {
            $n === 'concepto' => 'concepto',
            $n === 'propuesta' => 'propuesta',
            $n === 'cliente' => 'cliente',
            $n === 'importe' => 'importe',
            $n === 'iva' => 'iva',
            $n === 'irpf' => 'irpf',
            str_contains($n, 'fecha') && str_contains($n, 'envio') => 'fecha_envio',
            $n === 'estado' => 'estado',
            $n === 'nota' => 'nota',
            default => null,
        };
    }

    private function normalizeLabel(string $s): string
    {
        $s = mb_strtolower(trim($s));
        static $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return $s;
    }
}
