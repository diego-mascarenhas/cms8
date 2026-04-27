<?php

namespace Tests\Unit;

use App\Services\TaxIdentifierService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxIdentifierService::class)]
class TaxIdentifierServiceTest extends TestCase
{
    public function test_normalize_strips_separators_and_uppercases(): void
    {
        $s = new TaxIdentifierService;
        $this->assertSame('34293436E', $s->normalize(' 342.934.36-e '));
        $this->assertSame('20123456789', $s->normalize('20-12345678-9'));
    }

    public function test_spain_dni_nie_cif_and_stripe_type(): void
    {
        $s = new TaxIdentifierService;
        $this->assertTrue($s->isValidForCountry('ES', '34293436E'));
        $this->assertSame('es_cif', $s->resolveStripeTaxIdType('ES', '34293436E'));

        $this->assertTrue($s->isValidForCountry('ES', 'ES34293436E'));
        $this->assertSame('eu_vat', $s->resolveStripeTaxIdType('ES', 'ES34293436E'));

        $this->assertFalse($s->isValidForCountry('ES', '34293436A'));

        $this->assertTrue($s->isValidForCountry('ES', 'X0000000T'));
    }

    public function test_argentina_cuit_with_check_digit(): void
    {
        $s = new TaxIdentifierService;
        $this->assertTrue($s->isValidForCountry('AR', '20000000001'));
        $this->assertTrue($s->isValidArgentineCuit('20000000001'));
        $this->assertFalse($s->isValidForCountry('AR', '20000000002'));
        $this->assertSame('ar_cuit', $s->resolveStripeTaxIdType('AR', '20000000001'));
    }
}
