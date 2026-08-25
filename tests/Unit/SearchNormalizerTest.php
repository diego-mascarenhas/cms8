<?php

namespace Tests\Unit;

use App\Support\SearchNormalizer;
use Illuminate\Database\Connection;
use Mockery;
use PHPUnit\Framework\TestCase;

class SearchNormalizerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_normalize_lowercases_and_strips_spanish_accents(): void
    {
        $this->assertSame('jose maria', SearchNormalizer::normalize('  José MARÍA  '));
    }

    public function test_significant_tokens_drop_spanish_stopwords(): void
    {
        $this->assertSame(['abrazadera', '16', 'x', '27'], SearchNormalizer::significantTokens('abrazadera 16 x 27'));
    }

    public function test_normalize_matches_nfd_accent_fold_used_by_chat_sidebar_search(): void
    {
        if (! class_exists(\Normalizer::class))
        {
            $this->markTestSkipped('intl Normalizer extension required.');
        }

        foreach (['José MARÍA', 'Niño', 'Acción', 'Andrés', 'cañón', ' José '] as $input)
        {
            $this->assertSame(
                SearchNormalizer::normalize($input),
                $this->nfdAccentFoldLower($input),
                'Chat sidebar normalizeSearchText should match SearchNormalizer for: '.$input,
            );
        }
    }

    private function nfdAccentFoldLower(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_D);

        if ($decomposed === false)
        {
            return $value;
        }

        return preg_replace('/\p{M}/u', '', $decomposed) ?? $value;
    }

    public function test_like_pattern_wraps_normalized_term(): void
    {
        $this->assertSame('%foo%', SearchNormalizer::likePatternNormalized('FOO'));
    }

    public function test_contact_full_name_sql_uses_mysql_concat(): void
    {
        $sql = SearchNormalizer::contactFullNameLowerSql($this->connectionWithDriver('mysql'));

        $this->assertStringContainsString('concat', strtolower($sql));
        $this->assertStringNotContainsString('||', $sql);
    }

    public function test_contact_full_name_sql_uses_postgresql_concatenation(): void
    {
        $sql = SearchNormalizer::contactFullNameLowerSql($this->connectionWithDriver('pgsql'));

        $this->assertStringContainsString('||', $sql);
        $this->assertStringNotContainsString('concat', strtolower($sql));
    }

    public function test_contact_phone_like_sql_casts_per_driver(): void
    {
        $mysql = SearchNormalizer::contactPhoneLikeSql($this->connectionWithDriver('mysql'));
        $pgsql = SearchNormalizer::contactPhoneLikeSql($this->connectionWithDriver('pgsql'));

        $this->assertStringContainsString('as char', strtolower($mysql));
        $this->assertStringContainsString('as text', strtolower($pgsql));
    }

    private function connectionWithDriver(string $driver): Connection
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn($driver);

        return $connection;
    }
}
