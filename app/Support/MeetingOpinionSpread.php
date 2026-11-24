<?php

namespace App\Support;

class MeetingOpinionSpread
{
    /**
     * Share of discussed points that are polarized, atomized, or concentrated.
     * A point with both sides is polarized. A point with no side is atomized.
     * A point with only one side is concentrated.
     *
     * @param  array<string, mixed>  $summary
     * @return array{polarized: int, atomized: int, concentrated: int}|null
     */
    public static function percentages(array $summary): ?array
    {
        $points = [];
        foreach (is_array($summary['points'] ?? null) ? $summary['points'] : [] as $point)
        {
            if (! is_array($point) || trim((string) ($point['topic'] ?? '')) === '')
            {
                continue;
            }

            $points[] = $point;
        }

        if ($points === [])
        {
            foreach (is_array($summary['opinions'] ?? null) ? $summary['opinions'] : [] as $opinion)
            {
                if (! is_array($opinion))
                {
                    continue;
                }

                if (trim((string) ($opinion['text'] ?? $opinion['view'] ?? '')) !== '')
                {
                    return ['polarized' => 0, 'atomized' => 100, 'concentrated' => 0];
                }
            }

            return null;
        }

        $polarized = 0;
        $atomized = 0;
        $concentrated = 0;
        foreach ($points as $point)
        {
            $agree = self::names($point['agree'] ?? []);
            $disagree = self::names($point['disagree'] ?? []);
            if ($agree !== [] && $disagree !== [])
            {
                $polarized++;
            } elseif ($agree === [] && $disagree === [])
            {
                $atomized++;
            } else
            {
                $concentrated++;
            }
        }

        $total = count($points);

        return [
            'polarized' => (int) round(($polarized / $total) * 100),
            'atomized' => (int) round(($atomized / $total) * 100),
            'concentrated' => (int) round(($concentrated / $total) * 100),
        ];
    }

    /**
     * Objectives the team can mark. Stored marks win. Otherwise conclusions, then discussed points.
     *
     * @param  array<string, mixed>  $summary
     * @return list<array{text: string, marked: bool}>
     */
    public static function objectives(array $summary): array
    {
        $stored = [];
        foreach (is_array($summary['objectives'] ?? null) ? $summary['objectives'] : [] as $row)
        {
            if (! is_array($row))
            {
                continue;
            }

            $text = trim((string) ($row['text'] ?? ''));
            if ($text === '')
            {
                continue;
            }

            $stored[mb_strtolower($text)] = (bool) ($row['marked'] ?? false);
        }

        $texts = [];
        if ($stored !== [])
        {
            foreach (is_array($summary['objectives'] ?? null) ? $summary['objectives'] : [] as $row)
            {
                if (! is_array($row))
                {
                    continue;
                }

                $text = trim((string) ($row['text'] ?? ''));
                if ($text !== '')
                {
                    $texts[] = $text;
                }
            }
        } else
        {
            foreach (is_array($summary['conclusions'] ?? null) ? $summary['conclusions'] : [] as $row)
            {
                if (is_array($row) && trim((string) ($row['title'] ?? '')) !== '')
                {
                    $texts[] = trim((string) $row['title']);
                }
            }

            if ($texts === [])
            {
                foreach (is_array($summary['points'] ?? null) ? $summary['points'] : [] as $point)
                {
                    if (is_array($point) && trim((string) ($point['topic'] ?? '')) !== '')
                    {
                        $texts[] = trim((string) $point['topic']);
                    }
                }
            }
        }

        $objectives = [];
        foreach ($texts as $text)
        {
            $objectives[] = [
                'text' => $text,
                'marked' => $stored[mb_strtolower($text)] ?? false,
            ];
        }

        return $objectives;
    }

    /**
     * @return list<string>
     */
    private static function names(mixed $value): array
    {
        if (! is_array($value))
        {
            return [];
        }

        $names = [];
        foreach ($value as $name)
        {
            $name = trim((string) $name);
            if ($name !== '')
            {
                $names[] = $name;
            }
        }

        return $names;
    }
}
