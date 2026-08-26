<?php

namespace App\Support;

use App\Enums\ProductCatalogStatus;
use App\Helpers\WhatsAppNaturalCartPhrase;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Ranks WhatsApp-sellable products by how well a customer phrase matches name, code, barcode, OEM, or description.
 */
class WhatsAppProductRelevanceSearch
{
    private const CANDIDATE_LIMIT = 80;

    /**
     * @return array{products: Collection<int, Product>, all_tokens_matched: bool}
     */
    public static function search(int $teamId, string $needle, int $limit = 20): array
    {
        $needle = WhatsAppNaturalCartPhrase::sanitizeProductNeedle($needle);
        $empty = ['products' => collect(), 'all_tokens_matched' => false];

        if ($needle === '' || $teamId < 1)
        {
            return $empty;
        }

        $exact = self::findExactIdOrCode($teamId, $needle);
        if ($exact)
        {
            return ['products' => collect([$exact]), 'all_tokens_matched' => true];
        }

        $tokens = SearchNormalizer::significantTokens($needle);
        $candidates = self::candidates($teamId, $tokens);
        if ($candidates->isEmpty())
        {
            return $empty;
        }

        $scored = $candidates->map(function (Product $product) use ($tokens, $needle): array
        {
            return self::scoreProduct($product, $tokens, $needle);
        })->filter(fn (array $row): bool => $row['matched'] > 0)
            ->sort(function (array $a, array $b): int
            {
                return [$b['score'], $b['coverage'], $a['name']] <=> [$a['score'], $a['coverage'], $b['name']];
            })
            ->values();

        if ($scored->isEmpty())
        {
            return $empty;
        }

        $bestCoverage = (float) $scored->max('coverage');
        $allMatched = $bestCoverage >= 1.0;
        $kept = $allMatched
            ? $scored->filter(fn (array $row): bool => $row['coverage'] >= 1.0)
            : $scored->filter(fn (array $row): bool => $row['coverage'] >= max(0.4, $bestCoverage - 0.3));

        if ($kept->isEmpty())
        {
            $kept = $scored->take(5);
        }

        return [
            'products' => $kept->take($limit)->map(fn (array $row): Product => $row['product'])->values(),
            'all_tokens_matched' => $allMatched,
        ];
    }

    public static function find(int $teamId, string $needle): ?Product
    {
        return self::search($teamId, $needle, 1)['products']->first();
    }

    private static function findExactIdOrCode(int $teamId, string $needle): ?Product
    {
        if (preg_match('/^\d+$/', $needle) !== 1)
        {
            return null;
        }

        return self::sellable($teamId)
            ->where(function ($query) use ($needle): void
            {
                $query->where('id', (int) $needle)
                    ->orWhereRaw('LOWER(code) = ?', [mb_strtolower($needle)])
                    ->orWhereRaw('LOWER(barcode) = ?', [mb_strtolower($needle)]);
            })
            ->first();
    }

    /**
     * @param  list<string>  $tokens
     * @return Collection<int, Product>
     */
    private static function candidates(int $teamId, array $tokens): Collection
    {
        $primary = self::primaryToken($tokens);
        $query = self::sellable($teamId);

        if ($primary !== null)
        {
            self::constrainToken($query, $primary);
        } else
        {
            $numbers = array_values(array_filter($tokens, fn (string $token): bool => preg_match('/^\d+$/', $token) === 1));
            if ($numbers === [])
            {
                return collect();
            }

            foreach ($numbers as $number)
            {
                self::constrainToken($query, $number);
            }
        }

        $found = $query->limit(self::CANDIDATE_LIMIT)->get();
        if ($found->isNotEmpty() || $primary === null || mb_strlen($primary) < 4)
        {
            return $found;
        }

        $prefix = mb_substr($primary, 0, min(6, mb_strlen($primary)));
        $prefixQuery = self::sellable($teamId);
        self::constrainToken($prefixQuery, $prefix);

        return $prefixQuery->limit(self::CANDIDATE_LIMIT)->get();
    }

    /**
     * @param  list<string>  $tokens
     */
    private static function primaryToken(array $tokens): ?string
    {
        $alpha = array_values(array_filter(
            $tokens,
            fn (string $token): bool => preg_match('/^[a-z]{3,}/', $token) === 1,
        ));

        if ($alpha === [])
        {
            return null;
        }

        usort($alpha, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $alpha[0];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     */
    private static function constrainToken($query, string $token): void
    {
        $query->where(function ($constraint) use ($token): void
        {
            SearchNormalizer::applyColumnsNavbarConditions($constraint, ['name', 'code', 'barcode', 'oem', 'description'], $token);
        });
    }

    /**
     * @param  list<string>  $tokens
     * @return array{product: Product, score: int, coverage: float, matched: int, name: string}
     */
    private static function scoreProduct(Product $product, array $tokens, string $needle): array
    {
        $name = SearchNormalizer::normalize((string) $product->name);
        $code = SearchNormalizer::normalize((string) $product->code);
        $barcode = SearchNormalizer::normalize((string) ($product->barcode ?? ''));
        $oem = SearchNormalizer::normalize((string) ($product->oem ?? ''));
        $description = SearchNormalizer::normalize((string) ($product->description ?? ''));
        $normalizedNeedle = SearchNormalizer::normalize($needle);
        $tokenCount = max(1, count($tokens));
        $score = 0;
        $matched = 0;

        if ($code !== '' && ($code === $normalizedNeedle || in_array($code, $tokens, true)))
        {
            $score += 1000;
            $matched++;
        }

        if ($barcode !== '' && ($barcode === $normalizedNeedle || in_array($barcode, $tokens, true)))
        {
            $score += 1000;
            $matched++;
        }

        if ($oem !== '' && ($oem === $normalizedNeedle || str_contains($oem, $normalizedNeedle)))
        {
            $score += 900;
            $matched++;
        }

        foreach ($tokens as $token)
        {
            if (str_contains($name, $token))
            {
                $score += 12;
                $matched++;

                continue;
            }

            if ($code !== '' && str_contains($code, $token))
            {
                $score += 10;
                $matched++;

                continue;
            }

            if ($barcode !== '' && str_contains($barcode, $token))
            {
                $score += 10;
                $matched++;

                continue;
            }

            if ($oem !== '' && str_contains($oem, $token))
            {
                $score += 10;
                $matched++;

                continue;
            }

            if ($description !== '' && str_contains($description, $token))
            {
                $score += 3;
                $matched++;

                continue;
            }

            if (self::fuzzyMatchesName($name, $token))
            {
                $score += 6;
                $matched++;
            }
        }

        $phrase = implode(' ', $tokens);
        if ($phrase !== '' && str_contains($name, $phrase))
        {
            $score += 40;
        }

        if (isset($tokens[0]) && str_starts_with($name, $tokens[0]))
        {
            $score += 15;
        }

        $coverage = min(1.0, $matched / $tokenCount);
        $score += (int) round($coverage * 25);
        $score += self::numericProximityBonus($tokens, $name);

        return [
            'product' => $product,
            'score' => $score,
            'coverage' => $coverage,
            'matched' => $matched,
            'name' => $name,
        ];
    }

    /**
     * @param  list<string>  $tokens
     */
    private static function numericProximityBonus(array $tokens, string $normalizedName): int
    {
        $queryNumbers = [];
        foreach ($tokens as $token)
        {
            if (preg_match('/^\d+$/', $token) === 1)
            {
                $queryNumbers[] = (int) $token;
            }
        }

        if ($queryNumbers === [])
        {
            return 0;
        }

        preg_match_all('/\d+/', $normalizedName, $matches);
        $nameNumbers = array_map('intval', $matches[0] ?? []);
        if ($nameNumbers === [])
        {
            return 0;
        }

        $bonus = 0;
        foreach ($queryNumbers as $queryNumber)
        {
            if (in_array($queryNumber, $nameNumbers, true))
            {
                continue;
            }

            $nearest = min(array_map(fn (int $nameNumber): int => abs($nameNumber - $queryNumber), $nameNumbers));
            if ($nearest <= 10)
            {
                $bonus += max(0, 8 - $nearest);
            }
        }

        return $bonus;
    }

    private static function fuzzyMatchesName(string $normalizedName, string $token): bool
    {
        if (mb_strlen($token) < 4)
        {
            return false;
        }

        foreach (preg_split('/\s+/', $normalizedName) ?: [] as $word)
        {
            if ($word === '')
            {
                continue;
            }

            if (str_starts_with($word, $token) && mb_strlen($token) >= 4)
            {
                return true;
            }

            if (abs(mb_strlen($word) - mb_strlen($token)) > 2)
            {
                continue;
            }

            if (levenshtein($token, $word) <= 1)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    private static function sellable(int $teamId): \Illuminate\Database\Eloquent\Builder
    {
        return Product::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('catalog_status', ProductCatalogStatus::Publish)
            ->with(['category:id,name', 'currency:id,symbol']);
    }
}
