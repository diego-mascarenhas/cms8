<?php

namespace App\Services;

class PdfTextExtractor
{
    public function extract(string $absolutePath): ?string
    {
        $raw = file_get_contents($absolutePath);
        if (! is_string($raw) || ! str_starts_with($raw, '%PDF'))
        {
            return null;
        }

        $objects = $this->objects($raw);
        if ($objects === [])
        {
            return null;
        }

        $cmaps = [];
        $fontMaps = [];
        foreach ($objects as $id => $body)
        {
            if (str_contains($body, 'beginbfchar') || str_contains($body, 'beginbfrange'))
            {
                $cmaps[$id] = $this->cmap($body);
            }
        }

        foreach ($objects as $id => $body)
        {
            if (preg_match('/\/ToUnicode\s+(\d+)\s+0\s+R/', $body, $match) === 1)
            {
                $fontMaps[$id] = $cmaps[(int) $match[1]] ?? [];
            }
        }

        $lines = [];
        foreach ($this->pageIds($objects) as $pageId)
        {
            $page = $objects[$pageId] ?? '';
            $fonts = $this->pageFonts($page, $objects, $fontMaps);
            $glyphs = [];
            $state = ['font' => [], 'x' => 0.0, 'y' => 0.0];
            foreach ($this->contentIds($page) as $contentId)
            {
                $glyphs = array_merge($glyphs, $this->glyphs($objects[$contentId] ?? '', $fonts, $state));
            }
            $lines = array_merge($lines, $this->assemble($glyphs));
        }

        $text = trim(implode("\n", array_filter($lines, fn (string $line): bool => trim($line) !== '')));

        return $text !== '' ? $text : null;
    }

    /**
     * @return array<int, string>
     */
    private function objects(string $raw): array
    {
        preg_match_all('/(\d+)\s+0\s+obj\s*(.*?)endobj/s', $raw, $matches, PREG_SET_ORDER);
        $objects = [];

        foreach ($matches as $match)
        {
            $body = $match[2];
            $streamAt = strpos($body, 'stream');
            if ($streamAt === false)
            {
                $objects[(int) $match[1]] = $body;

                continue;
            }

            $dict = substr($body, 0, $streamAt);
            $dataStart = $streamAt + strlen('stream');
            if (($body[$dataStart] ?? '') === "\r")
            {
                $dataStart++;
            }
            if (($body[$dataStart] ?? '') === "\n")
            {
                $dataStart++;
            }
            $end = strpos($body, 'endstream', $dataStart);
            $chunk = rtrim($end === false ? substr($body, $dataStart) : substr($body, $dataStart, $end - $dataStart), "\r\n");
            $data = $this->inflate($dict, $chunk);

            if (preg_match('/\/Type\s*\/ObjStm\b/', $dict) === 1)
            {
                $objects += $this->objectStream($dict, $data);

                continue;
            }

            $objects[(int) $match[1]] = $data;
        }

        return $objects;
    }

    /**
     * @return array<int, string>
     */
    private function objectStream(string $dict, string $data): array
    {
        if (preg_match('/\/N\s+(\d+)/', $dict, $count) !== 1 || preg_match('/\/First\s+(\d+)/', $dict, $first) !== 1)
        {
            return [];
        }

        $total = (int) $count[1];
        $offset = (int) $first[1];
        preg_match_all('/(\d+)\s+(\d+)/', substr($data, 0, $offset), $pairs, PREG_SET_ORDER);
        $pairs = array_slice($pairs, 0, $total);
        $objects = [];

        foreach ($pairs as $index => $pair)
        {
            $start = $offset + (int) $pair[2];
            $end = isset($pairs[$index + 1]) ? $offset + (int) $pairs[$index + 1][2] : strlen($data);
            $objects[(int) $pair[1]] = substr($data, $start, max(0, $end - $start));
        }

        return $objects;
    }

    private function inflate(string $dict, string $chunk): string
    {
        if (! str_contains($dict, 'FlateDecode'))
        {
            return $chunk;
        }

        $inflated = @gzuncompress($chunk);
        if ($inflated === false)
        {
            $inflated = @gzinflate($chunk);
        }

        return is_string($inflated) && $inflated !== '' ? $inflated : $chunk;
    }

    /**
     * @param  array<int, string>  $objects
     * @return list<int>
     */
    private function pageIds(array $objects): array
    {
        foreach ($objects as $body)
        {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) === 1 && preg_match('/\/Pages\s+(\d+)\s+0\s+R/', $body, $match) === 1)
            {
                return $this->walkPages($objects, (int) $match[1], []);
            }
        }

        $ids = [];
        foreach ($objects as $id => $body)
        {
            if (preg_match('/\/Type\s*\/Page\b/', $body) === 1)
            {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param  array<int, string>  $objects
     * @param  array<int, true>  $seen
     * @return list<int>
     */
    private function walkPages(array $objects, int $id, array $seen): array
    {
        if (isset($seen[$id]))
        {
            return [];
        }

        $seen[$id] = true;
        $body = $objects[$id] ?? '';
        if (preg_match('/\/Type\s*\/Pages\b/', $body) === 1 && preg_match('/\/Kids\s*\[(.*?)\]/s', $body, $kids) === 1)
        {
            preg_match_all('/(\d+)\s+0\s+R/', $kids[1], $matches);
            $ids = [];
            foreach ($matches[1] as $child)
            {
                $ids = array_merge($ids, $this->walkPages($objects, (int) $child, $seen));
            }

            return $ids;
        }

        return preg_match('/\/Type\s*\/Page\b/', $body) === 1 ? [$id] : [];
    }

    /**
     * @param  array<int, string>  $objects
     * @param  array<int, array<string, string>>  $fontMaps
     * @return array<string, array<string, string>>
     */
    private function pageFonts(string $page, array $objects, array $fontMaps): array
    {
        $resources = $page;
        if (preg_match('/\/Resources\s+(\d+)\s+0\s+R/', $page, $reference) === 1)
        {
            $resources = $objects[(int) $reference[1]] ?? '';
        }

        $fontBlock = '';
        if (preg_match('/\/Font\s*<<(.*?)>>/s', $resources, $block) === 1)
        {
            $fontBlock = $block[1];
        } elseif (preg_match('/\/Font\s+(\d+)\s+0\s+R/', $resources, $reference) === 1)
        {
            $fontBlock = $objects[(int) $reference[1]] ?? '';
        }

        $fonts = [];
        preg_match_all('/\/(F\d+)\s+(\d+)\s+0\s+R/', $fontBlock, $matches, PREG_SET_ORDER);
        foreach ($matches as $match)
        {
            $fonts[$match[1]] = $fontMaps[(int) $match[2]] ?? [];
        }

        return $fonts;
    }

    /**
     * @return list<int>
     */
    private function contentIds(string $page): array
    {
        if (preg_match('/\/Contents\s*\[(.*?)\]/s', $page, $list) === 1)
        {
            preg_match_all('/(\d+)\s+0\s+R/', $list[1], $matches);

            return array_map('intval', $matches[1]);
        }

        if (preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $page, $one) === 1)
        {
            return [(int) $one[1]];
        }

        return [];
    }

    /**
     * @param  array<string, array<string, string>>  $fonts
     * @param  array{font: array<string, string>, x: float, y: float}  $state
     * @return list<array{x: float, y: float, text: string}>
     */
    private function glyphs(string $content, array $fonts, array &$state): array
    {
        preg_match_all(
            '/\/(F\d+)\s+[-\d.]+\s+Tf|([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+Tm|([-\d.]+)\s+([-\d.]+)\s+Td|<([0-9A-Fa-f]+)>\s*Tj|\(((?:\\\\.|[^\\\\)])*)\)\s*Tj/s',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        $font = $state['font'];
        $x = $state['x'];
        $y = $state['y'];
        $glyphs = [];
        foreach ($matches as $match)
        {
            if (($match[1] ?? '') !== '')
            {
                $font = $fonts[$match[1]] ?? [];

                continue;
            }

            if (($match[6] ?? '') !== '')
            {
                $x = (float) $match[6];
                $y = (float) $match[7];

                continue;
            }

            if (($match[8] ?? '') !== '')
            {
                $x += (float) $match[8];
                $y += (float) $match[9];

                continue;
            }

            $text = ($match[10] ?? '') !== ''
                ? $this->mapped($match[10], $font)
                : $this->literal($match[11] ?? '', $font);
            if ($text === '')
            {
                continue;
            }

            $glyphs[] = ['x' => $x, 'y' => $y, 'text' => $text];
        }

        $state = ['font' => $font, 'x' => $x, 'y' => $y];

        return $glyphs;
    }

    /**
     * @param  list<array{x: float, y: float, text: string}>  $glyphs
     * @return list<string>
     */
    private function assemble(array $glyphs): array
    {
        $rows = [];
        $order = [];
        foreach ($glyphs as $glyph)
        {
            $key = null;
            $best = 3.5;
            foreach ($order as $existing)
            {
                $delta = abs((float) $existing - $glyph['y']);
                if ($delta <= $best)
                {
                    $key = $existing;
                    $best = $delta;
                }
            }
            if ($key === null)
            {
                $key = number_format($glyph['y'], 1, '.', '');
                $order[] = $key;
                $rows[$key] = [];
            }
            $rows[$key][] = $glyph;
        }

        $lines = [];
        foreach ($order as $key)
        {
            $row = $rows[$key];
            usort($row, fn (array $left, array $right): int => $left['x'] <=> $right['x']);
            $text = '';
            $previousX = null;
            foreach ($row as $glyph)
            {
                if ($previousX !== null && ($glyph['x'] - $previousX) > 24)
                {
                    $text .= "\t";
                }
                $text .= $glyph['text'];
                $previousX = $glyph['x'];
            }
            $lines[] = trim($text);
        }

        return $lines;
    }

    /**
     * @param  array<string, string>  $font
     */
    private function mapped(string $hex, array $font): string
    {
        $hex = strtoupper($hex);
        $width = 4;
        $text = '';
        foreach (str_split($hex, $width) as $code)
        {
            if (strlen($code) < $width)
            {
                continue;
            }
            $text .= $font[$code] ?? '';
        }

        return $text;
    }

    /**
     * @param  array<string, string>  $font
     */
    private function literal(string $value, array $font): string
    {
        $decoded = preg_replace_callback(
            '/\\\\([nrtbf()\\\\]|[0-7]{1,3})/',
            function (array $match): string
            {
                return match ($match[1])
                {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'f' => "\f",
                    '(', ')', '\\' => $match[1],
                    default => chr(octdec($match[1])),
                };
            },
            $value,
        ) ?? $value;

        return $this->mapLiteral($decoded, $font);
    }

    /**
     * Identity-H fonts store each glyph as a UTF-16 code that still needs ToUnicode.
     *
     * @param  array<string, string>  $font
     */
    private function mapLiteral(string $decoded, array $font): string
    {
        if ($font === [] || $decoded === '')
        {
            return $decoded;
        }

        $units = $decoded;
        if (str_starts_with($units, "\xFE\xFF"))
        {
            $units = substr($units, 2);
        } elseif (! $this->isUtf16Be($units))
        {
            return $decoded;
        }

        $text = '';
        foreach (str_split($units, 2) as $pair)
        {
            if (strlen($pair) < 2)
            {
                continue;
            }
            $text .= $font[strtoupper(bin2hex($pair))] ?? '';
        }

        return $text;
    }

    private function isUtf16Be(string $value): bool
    {
        $length = strlen($value);
        if ($length < 2 || $length % 2 !== 0)
        {
            return false;
        }

        for ($index = 0; $index < $length; $index += 2)
        {
            if ($value[$index] !== "\x00")
            {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function cmap(string $body): array
    {
        $map = [];
        if (preg_match_all('/beginbfchar\s*(.*?)\s*endbfchar/s', $body, $blocks) === false)
        {
            return $map;
        }

        foreach ($blocks[1] as $block)
        {
            preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER);
            foreach ($pairs as $pair)
            {
                $map[strtoupper($pair[1])] = $this->unicode($pair[2]);
            }
        }

        preg_match_all('/beginbfrange\s*(.*?)\s*endbfrange/s', $body, $ranges);
        foreach ($ranges[1] as $block)
        {
            preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER);
            foreach ($pairs as $pair)
            {
                $start = hexdec($pair[1]);
                $end = hexdec($pair[2]);
                $dest = hexdec($pair[3]);
                $width = strlen($pair[1]);
                for ($code = $start; $code <= $end; $code++)
                {
                    $map[strtoupper(str_pad(dechex($code), $width, '0', STR_PAD_LEFT))] = $this->unicode(str_pad(dechex($dest + ($code - $start)), 4, '0', STR_PAD_LEFT));
                }
            }
        }

        return $map;
    }

    private function unicode(string $hex): string
    {
        $text = '';
        foreach (str_split(strtoupper($hex), 4) as $unit)
        {
            if (strlen($unit) < 4)
            {
                continue;
            }
            $code = hexdec($unit);
            if ($code <= 0)
            {
                continue;
            }
            $text .= mb_chr($code, 'UTF-8');
        }

        return $text;
    }
}
