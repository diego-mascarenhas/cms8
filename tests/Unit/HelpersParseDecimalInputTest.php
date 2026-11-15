<?php

namespace Tests\Unit;

use App\Helpers\Helpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HelpersParseDecimalInputTest extends TestCase
{
    #[Test]
    #[DataProvider('decimalProvider')]
    public function it_parses_spanish_and_english_decimals(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, Helpers::parseDecimalInput($input));
    }

    public static function decimalProvider(): array
    {
        return [
            'spanish comma' => ['16,00', '16.00'],
            'english dot' => ['16.00', '16.00'],
            'thousands spanish' => ['1.234,56', '1234.56'],
            'thousands english' => ['1,234.56', '1234.56'],
            'integer' => ['16', '16.00'],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }
}
