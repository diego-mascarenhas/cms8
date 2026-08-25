<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;

/**
 * Normalizes global navbar search input for case- and (on PostgreSQL) accent-insensitive matching.
 *
 * PostgreSQL: uses {@code unaccent} only if the extension is installed; otherwise falls back to ILIKE-style matching.
 * Other drivers: case folding via SQL lower(); accent matching follows collation / ASCII-normalized pattern OR original substring.
 */
class SearchNormalizer
{
    /** @var array<string, bool> */
    private static array $pgsqlUnaccentByConnectionName = [];

    public static function normalize(string $value): string
    {
        $value = trim($value);

        return mb_strtolower(Str::ascii($value, 'es'), 'UTF-8');
    }

    public static function likePatternNormalized(string $term): string
    {
        return '%'.self::normalize($term).'%';
    }

    /**
     * Tokens for catalog search, without Spanish/English filler words.
     *
     * @return list<string>
     */
    public static function significantTokens(string $raw): array
    {
        $parts = preg_split('/[\s,.;:\/+]+/u', self::normalize($raw)) ?: [];
        $stopwords = [
            'para', 'un', 'una', 'unos', 'unas', 'de', 'del', 'el', 'la', 'los', 'las',
            'y', 'o', 'en', 'con', 'por', 'a', 'al', 'que', 'se', 'su', 'the', 'for', 'and',
        ];
        $tokens = [];

        foreach ($parts as $part)
        {
            if ($part === '' || in_array($part, $stopwords, true))
            {
                continue;
            }

            $tokens[] = $part;
        }

        return $tokens !== [] ? array_values(array_unique($tokens)) : [self::normalize($raw)];
    }

    /**
     * @internal Testing only
     */
    public static function flushUnaccentCache(): void
    {
        self::$pgsqlUnaccentByConnectionName = [];
    }

    private static function pgsqlUnaccentIsAvailable(Connection $connection): bool
    {
        if ($connection->getDriverName() !== 'pgsql')
        {
            return false;
        }

        $cacheKey = $connection->getName();

        if (array_key_exists($cacheKey, self::$pgsqlUnaccentByConnectionName))
        {
            return self::$pgsqlUnaccentByConnectionName[$cacheKey];
        }

        try
        {
            $row = $connection->selectOne(
                'select exists(select 1 from pg_catalog.pg_extension where extname = ?) as ok',
                ['unaccent'],
            );
            $ok = false;

            if ($row !== null)
            {
                $ok = is_object($row)
                    ? (bool) ($row->ok ?? false)
                    : (bool) ($row['ok'] ?? false);
            }

            self::$pgsqlUnaccentByConnectionName[$cacheKey] = $ok;
        } catch (\Throwable)
        {
            self::$pgsqlUnaccentByConnectionName[$cacheKey] = false;
        }

        return self::$pgsqlUnaccentByConnectionName[$cacheKey];
    }

    private static function sqlCastComparableString(Connection $connection): string
    {
        return match ($connection->getDriverName())
        {
            'mysql' => 'char',
            default => 'text',
        };
    }

    /**
     * Contact full name expression (cross-database).
     */
    private static function sqlContactFullName(Connection $connection): string
    {
        return match ($connection->getDriverName())
        {
            'mysql' => "trim(concat(coalesce(name, ''), ' ', coalesce(surname, '')))",
            default => "trim(coalesce(cast(name as text), '') || ' ' || coalesce(cast(surname as text), ''))",
        };
    }

    /**
     * Lowercased, trimmed contact full name for equality / LIKE (MySQL and PostgreSQL).
     */
    public static function contactFullNameLowerSql(Connection $connection): string
    {
        return 'lower('.self::sqlContactFullName($connection).')';
    }

    /**
     * Lowercased, trimmed coalesced contact text column (name, surname, email).
     */
    public static function contactTextColumnLowerSql(string $column, Connection $connection): string
    {
        self::assertSafeIdentifier($column);

        return 'lower(trim('.self::sqlCoalesceColumn($column, $connection).'))';
    }

    /**
     * Phone column cast to string for LIKE (bigint-safe on MySQL and PostgreSQL).
     */
    public static function contactPhoneLikeSql(Connection $connection): string
    {
        return match ($connection->getDriverName())
        {
            'mysql' => "cast(coalesce(phone, '') as char)",
            default => "coalesce(cast(phone as text), '')",
        };
    }

    private static function sqlCoalesceColumn(string $column, Connection $connection): string
    {
        self::assertSafeIdentifier($column);

        return match ($connection->getDriverName())
        {
            'mysql' => 'coalesce(cast('.$column.' as char), \'\')',
            default => 'coalesce(cast('.$column.' as text), \'\')',
        };
    }

    /**
     * Single text column (e.g. collaborator name).
     */
    public static function applyCollaboratorNameCondition(EloquentBuilder|QueryBuilder $query, string $term): void
    {
        $connection = self::connectionFromBuilder($query);
        self::appendTextMatchOr($query, self::sqlCoalesceColumn('name', $connection), $term, false);
    }

    /**
     * Navbar contact search (members): concatenated full name, nombre, apellido (each with same rules), email, phone.
     */
    public static function applyContactNavbarConditions(EloquentBuilder|QueryBuilder $query, string $term): void
    {
        $query->where(function (EloquentBuilder|QueryBuilder $q) use ($term)
        {
            $connection = self::connectionFromBuilder($q);
            $fullName = self::sqlContactFullName($connection);
            $emailExpr = self::sqlCoalesceColumn('email', $connection);
            $phoneExpr = match ($connection->getDriverName())
            {
                'mysql' => 'cast(coalesce(phone, \'\') as char)',
                default => 'coalesce(cast(phone as text), \'\')',
            };

            self::appendTextMatchOr($q, $fullName, $term, false);
            self::appendTextMatchOr($q, self::sqlCoalesceColumn('name', $connection), $term, true);
            self::appendTextMatchOr($q, self::sqlCoalesceColumn('surname', $connection), $term, true);
            self::appendTextMatchOr($q, $emailExpr, $term, true);
            $q->orWhereRaw($phoneExpr.' like ?', ['%'.$term.'%']);
        });
    }

    /**
     * Contact list DataTables global search on the {@code name} column: same identity matching as the navbar
     * ({@see applyContactNavbarConditions}) plus linked enterprises' commercial {@code name}.
     *
     * @param  EloquentBuilder<\App\Models\Contact>|QueryBuilder  $query
     */
    public static function applyContactDataTableNameColumnConditions(EloquentBuilder|QueryBuilder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '')
        {
            return;
        }

        $query->where(function (EloquentBuilder|QueryBuilder $outer) use ($term): void
        {
            self::applyContactNavbarConditions($outer, $term);
            $outer->orWhereHas('enterprises', function (EloquentBuilder $enterpriseQuery) use ($term): void
            {
                $connection = self::connectionFromBuilder($enterpriseQuery);
                self::appendTextMatchOr($enterpriseQuery, self::sqlCoalesceColumn('name', $connection), $term, false);
            });
        });
    }

    /**
     * Enterprise + billing-style columns (simple identifiers).
     *
     * @param  array<int, string>  $columns
     */
    public static function applyColumnsNavbarConditions(EloquentBuilder|QueryBuilder $query, array $columns, string $term, ?string $phoneColumn = null): void
    {
        $query->where(function (EloquentBuilder|QueryBuilder $q) use ($columns, $term, $phoneColumn)
        {
            $connection = self::connectionFromBuilder($q);
            $first = true;

            foreach ($columns as $column)
            {
                self::assertSafeIdentifier($column);
                $expr = self::sqlCoalesceColumn($column, $connection);
                self::appendTextMatchOr($q, $expr, $term, ! $first);
                $first = false;
            }

            if ($phoneColumn !== null)
            {
                self::assertSafeIdentifier($phoneColumn);
                $phoneExpr = match ($connection->getDriverName())
                {
                    'mysql' => 'cast(coalesce('.$phoneColumn.', \'\') as char)',
                    default => 'coalesce(cast('.$phoneColumn.' as text), \'\')',
                };
                $q->orWhereRaw($phoneExpr.' like ?', ['%'.$term.'%']);
            }
        });
    }

    /**
     * Enterprises: commercial name / code / email / phone plus razón social ({@see EnterpriseBillingAddress::$name}).
     *
     * @param  EloquentBuilder<\App\Models\Enterprise>  $query
     */
    public static function applyEnterpriseNavbarConditions(EloquentBuilder $query, string $term): void
    {
        $query->where(function (EloquentBuilder $w) use ($term): void
        {
            $w->where(function (EloquentBuilder $e) use ($term): void
            {
                self::applyColumnsNavbarConditions($e, ['name', 'code', 'email'], $term, 'phone');
            });
            $w->orWhereHas('enterpriseBillingAddresses', function (EloquentBuilder $billing) use ($term): void
            {
                $billing->where(function (EloquentBuilder $b) use ($term): void
                {
                    $connection = self::connectionFromBuilder($b);
                    self::appendTextMatchOr($b, self::sqlCoalesceColumn('name', $connection), $term, false);
                });
            });
        });
    }

    /**
     * Client list DataTables global search on the displayed enterprise {@code name} column (active clients).
     * Same matching rules as navbar enterprises ({@see applyEnterpriseNavbarConditions}).
     *
     * @param  EloquentBuilder<\App\Models\Enterprise>  $query
     */
    public static function applyClientDataTableNameColumnConditions(EloquentBuilder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '')
        {
            return;
        }

        self::applyEnterpriseNavbarConditions($query, $term);
    }

    public static function applyServiceNavbarConditions(EloquentBuilder|QueryBuilder $query, string $term): void
    {
        $query->where(function (EloquentBuilder|QueryBuilder $q) use ($term)
        {
            $connection = self::connectionFromBuilder($q);
            $desc = self::sqlCoalesceColumn('description', $connection);
            $dataExpr = match ($connection->getDriverName())
            {
                'mysql' => 'coalesce(cast(data as char), \'\')',
                default => 'coalesce(cast(data as text), \'\')',
            };

            self::appendTextMatchOr($q, $desc, $term, false);
            self::appendTextMatchOr($q, $dataExpr, $term, true);
        });
    }

    public static function applyProjectNavbarConditions(EloquentBuilder|QueryBuilder $query, string $term): void
    {
        $query->where(function (EloquentBuilder|QueryBuilder $q) use ($term)
        {
            $connection = self::connectionFromBuilder($q);
            self::appendTextMatchOr($q, self::sqlCoalesceColumn('name', $connection), $term, false);
            self::appendTextMatchOr($q, self::sqlCoalesceColumn('description', $connection), $term, true);
        });
    }

    private static function appendTextMatchOr(EloquentBuilder|QueryBuilder $query, string $expression, string $term, bool $or): void
    {
        $connection = self::connectionFromBuilder($query);
        $patternNorm = self::likePatternNormalized($term);
        $patternOrig = '%'.$term.'%';

        if (self::pgsqlUnaccentIsAvailable($connection))
        {
            $sql = 'lower(unaccent(cast(('.$expression.') as text))) like lower(unaccent(?))';
            if ($or)
            {
                $query->orWhereRaw($sql, [$patternNorm]);
            } else
            {
                $query->whereRaw($sql, [$patternNorm]);
            }

            return;
        }

        $cast = self::sqlCastComparableString($connection);

        $branch = function (EloquentBuilder|QueryBuilder $w) use ($expression, $patternNorm, $patternOrig, $cast, $connection): void
        {
            $foldedLower = self::sqlAccentFoldLower($expression, $cast, $connection);
            $castExpr = 'cast(('.$expression.') as '.$cast.')';

            if ($connection->getDriverName() === 'pgsql')
            {
                $w->whereRaw($foldedLower.' ilike ?', [$patternNorm])
                    ->orWhereRaw($castExpr.' ilike ?', [$patternOrig]);

                return;
            }

            $w->whereRaw($foldedLower.' like ?', [$patternNorm])
                ->orWhereRaw($castExpr.' like ?', [$patternOrig]);
        };

        if ($or)
        {
            $query->orWhere($branch);
        } else
        {
            $query->where($branch);
        }
    }

    /**
     * Lowercase + strip Latin accents without DB extensions (Spanish-focused).
     * Keeps search term alignment with {@see normalize()} on the input side.
     *
     * @param  string  $expression  SQL fragment evaluating to a comparable string (often already coalesce/cast).
     */
    private static function sqlAccentFoldLower(string $expression, string $cast, Connection $connection): string
    {
        $lowered = 'lower(cast(('.$expression.') as '.$cast.'))';

        return match ($connection->getDriverName())
        {
            'pgsql' => 'translate('.$lowered.', '.self::escapeSqlUtf8Literal(self::accentTranslateFrom()).', '.self::escapeSqlUtf8Literal(self::accentTranslateTo()).')',
            'sqlite' => self::sqlAccentFoldReplaceChain($lowered, self::accentFoldReplacePairsSqlite()),
            default => self::sqlAccentFoldReplaceChain($lowered, self::accentFoldReplacePairs()),
        };
    }

    /**
     * Short replace chain for SQLite (no {@code translate()} in older embedded versions; avoids parser stack overflow).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private static function accentFoldReplacePairsSqlite(): array
    {
        return [
            ['á', 'a'], ['é', 'e'], ['í', 'i'], ['ó', 'o'], ['ú', 'u'],
            ['à', 'a'], ['è', 'e'], ['ì', 'i'], ['ò', 'o'], ['ù', 'u'],
            ['ü', 'u'], ['ñ', 'n'], ['ç', 'c'],
        ];
    }

    /**
     * Source characters for {@see translate()} (must match {@see accentTranslateTo()} length).
     */
    private static function accentTranslateFrom(): string
    {
        return 'áàäâãåéèëêíìïîóòöôõúùüûñçýÿ';
    }

    private static function accentTranslateTo(): string
    {
        return 'aaaaaaeeeeiiiioooooouuuuuncyy';
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $pairs
     */
    private static function sqlAccentFoldReplaceChain(string $loweredSql, array $pairs): string
    {
        $sql = $loweredSql;

        foreach ($pairs as [$from, $to])
        {
            $sql = 'replace('.$sql.', '.self::escapeSqlUtf8Literal($from).', '.self::escapeSqlUtf8Literal($to).')';
        }

        return $sql;
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private static function accentFoldReplacePairs(): array
    {
        return [
            ['á', 'a'], ['à', 'a'], ['ä', 'a'], ['â', 'a'], ['ã', 'a'], ['å', 'a'],
            ['é', 'e'], ['è', 'e'], ['ë', 'e'], ['ê', 'e'],
            ['í', 'i'], ['ì', 'i'], ['ï', 'i'], ['î', 'i'],
            ['ó', 'o'], ['ò', 'o'], ['ö', 'o'], ['ô', 'o'], ['õ', 'o'],
            ['ú', 'u'], ['ù', 'u'], ['ü', 'u'], ['û', 'u'],
            ['ñ', 'n'],
            ['ç', 'c'],
            ['ý', 'y'], ['ÿ', 'y'],
        ];
    }

    private static function escapeSqlUtf8Literal(string $char): string
    {
        return "'".str_replace("'", "''", $char)."'";
    }

    private static function connectionFromBuilder(EloquentBuilder|QueryBuilder $query): Connection
    {
        $connection = $query->getConnection();

        if (! $connection instanceof Connection)
        {
            throw new \LogicException('SearchNormalizer expects Illuminate\Database\Connection.');
        }

        return $connection;
    }

    private static function assertSafeIdentifier(string $name): void
    {
        if ($name === '' || preg_match('/^[a-z_][a-z0-9_]*$/i', $name) !== 1)
        {
            throw new \InvalidArgumentException('Invalid SQL identifier for search column.');
        }
    }
}
