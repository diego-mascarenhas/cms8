<?php

namespace Tests\Unit;

use App\Helpers\Helpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HelpersFormatHoursHumanTest extends TestCase
{
    #[Test]
    #[DataProvider('hoursProvider')]
    public function it_formats_decimal_hours_as_human_duration(mixed $hours, string $expected): void
    {
        $this->assertSame($expected, Helpers::formatHoursHuman($hours));
    }

    public static function hoursProvider(): array
    {
        return [
            'one and a half hours' => [1.5, '1 h 30 min'],
            'whole hours' => [2, '2 h'],
            'minutes only' => [0.25, '15 min'],
            'zero' => [0, '0 min'],
            'null' => [null, '—'],
            'empty string' => ['', '—'],
            'negative' => [-1, '—'],
        ];
    }
}
