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

    #[Test]
    #[DataProvider('halfHourProvider')]
    public function it_formats_hours_rounded_up_to_half_hour_steps(mixed $hours, string $expected): void
    {
        $this->assertSame($expected, Helpers::formatHoursHuman($hours, true));
    }

    public static function halfHourProvider(): array
    {
        return [
            '54 minutes becomes 1 hour' => [0.9, '1 h'],
            '1 h 12 min becomes 1 h 30 min' => [1.2, '1 h 30 min'],
            'exact half hour stays' => [0.5, '30 min'],
            'exact hour stays' => [1, '1 h'],
            'one minute becomes 30 min' => [1 / 60, '30 min'],
            'null' => [null, '—'],
        ];
    }
}
