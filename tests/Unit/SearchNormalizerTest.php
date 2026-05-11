<?php

namespace Tests\Unit;

use App\Support\SearchNormalizer;
use PHPUnit\Framework\TestCase;

class SearchNormalizerTest extends TestCase
{
    public function test_normalize_lowercases_and_strips_spanish_accents(): void
    {
        $this->assertSame('jose maria', SearchNormalizer::normalize('  José MARÍA  '));
    }

    public function test_like_pattern_wraps_normalized_term(): void
    {
        $this->assertSame('%foo%', SearchNormalizer::likePatternNormalized('FOO'));
    }
}
