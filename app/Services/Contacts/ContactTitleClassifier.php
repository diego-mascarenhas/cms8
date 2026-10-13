<?php

namespace App\Services\Contacts;

use Illuminate\Support\Str;

class ContactTitleClassifier
{
    /**
     * Equivalent top-job titles collapse to one filter label.
     * Longer phrases are matched before shorter ones.
     *
     * @var array<string, list<string>>
     */
    private const GROUPS = [
        'CEO' => [
            'chief executive officer',
            'chief executive',
            'director general',
            'gerente general',
            'managing director',
            'presidente ejecutivo',
            'socio fundador',
            'socia fundadora',
            'co founder',
            'presidente',
            'presidenta',
            'president',
            'fundadora',
            'fundador',
            'founder',
            'propietaria',
            'propietario',
            'duena',
            'dueno',
            'owner',
            'ceo',
        ],
        'CTO' => [
            'chief technology officer',
            'chief technical officer',
            'cto',
        ],
        'CFO' => [
            'chief financial officer',
            'cfo',
        ],
        'COO' => [
            'chief operating officer',
            'chief operations officer',
            'coo',
        ],
        'CMO' => [
            'chief marketing officer',
            'cmo',
        ],
        'CCO' => [
            'chief commercial officer',
            'chief customer officer',
            'cco',
        ],
        'CIO' => [
            'chief information officer',
            'cio',
        ],
        'CPO' => [
            'chief product officer',
            'cpo',
        ],
        'CRO' => [
            'chief revenue officer',
            'cro',
        ],
        'CAO' => [
            'chief accounting officer',
            'chief administrative officer',
            'cao',
        ],
    ];

    /**
     * @var list<string>
     */
    private const GLUE = ['and', 'y', 'e', 'of', 'de', 'del', 'en', 'at', 'the'];

    /**
     * @var list<string>
     */
    private const SMALL_WORDS = ['de', 'del', 'y', 'e', 'of', 'and', 'the', 'en', 'a'];

    /**
     * @var list<string>
     */
    private const ACRONYMS = ['ceo', 'cto', 'cfo', 'coo', 'cmo', 'cco', 'cio', 'cpo', 'cro', 'cao', 'vp', 'svp', 'evp'];

    public static function classify(?string $title, ?string $organization = null): ?string
    {
        $raw = trim((string) $title);
        if ($raw === '')
        {
            return null;
        }

        $cleaned = self::cleanup(self::stripCompanyTail(self::stripOrganization($raw, $organization)));
        if ($cleaned === '')
        {
            return null;
        }

        $canonical = self::matchCanonical(self::fold($cleaned));
        if ($canonical !== null)
        {
            return $canonical;
        }

        $pretty = self::pretty($cleaned);

        return $pretty !== '' ? mb_substr($pretty, 0, 255) : null;
    }

    public static function organizationFromData(mixed $data): ?string
    {
        $decoded = json_decode(json_encode($data ?? new \stdClass), true);
        if (! is_array($decoded))
        {
            return null;
        }

        $candidates = [
            $decoded['apollo']['organization_name'] ?? null,
            $decoded['organization_name'] ?? null,
        ];

        foreach ($candidates as $candidate)
        {
            if (is_string($candidate) && trim($candidate) !== '')
            {
                return trim($candidate);
            }
        }

        return null;
    }

    private static function stripOrganization(string $title, ?string $organization): string
    {
        $organization = trim((string) $organization);
        if ($organization === '')
        {
            return $title;
        }

        $pattern = '/\b'.preg_quote($organization, '/').'\b/iu';
        $stripped = preg_replace($pattern, ' ', $title);

        return is_string($stripped) ? $stripped : $title;
    }

    private static function stripCompanyTail(string $title): string
    {
        $patterns = [
            '/\s+(at|@)\s+.+$/iu',
            '/\s+[|·•–—]\s+.+$/u',
            '/\s+-\s+.+$/u',
            '/,\s+.+$/u',
        ];

        $stripped = $title;
        foreach ($patterns as $pattern)
        {
            $next = preg_replace($pattern, '', $stripped);
            if (is_string($next))
            {
                $stripped = $next;
            }
        }

        return $stripped;
    }

    private static function cleanup(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value, " \t\n\r\0\x0B,;|·•-–—/&");
        $value = preg_replace('/\s+(at|de|del|en|of|y|and|the|@)$/iu', '', $value) ?? $value;

        return trim($value, " \t\n\r\0\x0B,;|·•-–—/&");
    }

    private static function fold(string $value): string
    {
        $value = preg_replace_callback('/\bc\.([a-z])\.o\.?\b/iu', function (array $matches): string
        {
            return 'c'.mb_strtolower($matches[1]).'o';
        }, $value) ?? $value;
        $ascii = Str::ascii($value);
        $ascii = mb_strtolower($ascii);
        $ascii = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '';

        return trim(preg_replace('/\s+/', ' ', $ascii) ?? '');
    }

    private static function matchCanonical(string $folded): ?string
    {
        $words = $folded === '' ? [] : explode(' ', $folded);
        if ($words === [])
        {
            return null;
        }

        $synonyms = self::synonyms();
        $label = null;
        $count = count($words);
        $index = 0;

        while ($index < $count)
        {
            if (in_array($words[$index], self::GLUE, true))
            {
                $index++;

                continue;
            }

            $hit = null;
            foreach ($synonyms as $synonym)
            {
                $length = count($synonym['words']);
                if ($index + $length > $count)
                {
                    continue;
                }
                if (array_slice($words, $index, $length) === $synonym['words'])
                {
                    $hit = $synonym;
                    break;
                }
            }

            if ($hit === null)
            {
                return $label;
            }

            if ($label !== null && $hit['label'] !== $label)
            {
                return $label;
            }

            $label = $hit['label'];
            $index += count($hit['words']);
            $sawCompanyGlue = false;
        }

        return $label;
    }

    /**
     * @return list<array{label: string, words: list<string>}>
     */
    private static function synonyms(): array
    {
        $list = [];
        foreach (self::GROUPS as $label => $synonyms)
        {
            foreach ($synonyms as $synonym)
            {
                $list[] = [
                    'label' => $label,
                    'words' => explode(' ', $synonym),
                ];
            }
        }

        usort($list, function (array $left, array $right): int
        {
            return count($right['words']) <=> count($left['words']);
        });

        return $list;
    }

    private static function pretty(string $value): string
    {
        $words = preg_split('/\s+/u', self::cleanup($value)) ?: [];
        $out = [];

        foreach ($words as $index => $word)
        {
            if ($word === '')
            {
                continue;
            }

            $folded = self::fold($word);
            if (in_array($folded, self::ACRONYMS, true))
            {
                $out[] = mb_strtoupper($folded);

                continue;
            }

            if ($index > 0 && in_array($folded, self::SMALL_WORDS, true))
            {
                $out[] = $folded;

                continue;
            }

            $out[] = mb_convert_case(mb_strtolower($word), MB_CASE_TITLE, 'UTF-8');
        }

        return implode(' ', $out);
    }
}
