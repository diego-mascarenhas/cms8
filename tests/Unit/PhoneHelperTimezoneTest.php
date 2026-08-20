<?php

namespace Tests\Unit;

use App\Helpers\PhoneHelper;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class PhoneHelperTimezoneTest extends TestCase
{
    public function test_argentina_phone_uses_buenos_aires(): void
    {
        $clock = PhoneHelper::clockForPhone('+54 9 11 6728 4492');

        $this->assertNotNull($clock);
        $this->assertSame('54', $clock['calling_code']);
        $this->assertSame('AR', $clock['country']);
        $this->assertSame('America/Argentina/Buenos_Aires', $clock['timezone']);
    }

    public function test_spain_phone_uses_madrid(): void
    {
        $clock = PhoneHelper::clockForPhone('34722372858');

        $this->assertNotNull($clock);
        $this->assertSame('34', $clock['calling_code']);
        $this->assertSame('ES', $clock['country']);
        $this->assertSame('Europe/Madrid', $clock['timezone']);
    }

    public function test_portugal_is_not_confused_with_a_shorter_prefix(): void
    {
        $clock = PhoneHelper::clockForPhone('351912345678');

        $this->assertNotNull($clock);
        $this->assertSame('351', $clock['calling_code']);
        $this->assertSame('Europe/Lisbon', $clock['timezone']);
    }

    public function test_spain_and_argentina_differ_by_five_hours_in_august(): void
    {
        $inSpain = new DateTimeImmutable('2026-08-20 15:00:00', new DateTimeZone('Europe/Madrid'));
        $inArgentina = $inSpain->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires'));

        $this->assertSame('10:00', $inArgentina->format('H:i'));
        $this->assertSame('2026-08-20', $inArgentina->format('Y-m-d'));
    }
}
