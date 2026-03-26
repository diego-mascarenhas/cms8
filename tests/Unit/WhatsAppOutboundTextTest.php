<?php

namespace Tests\Unit;

use App\Helpers\WhatsAppOutboundText;
use PHPUnit\Framework\TestCase;

class WhatsAppOutboundTextTest extends TestCase
{
    public function test_strips_bold_markdown_around_https_url(): void
    {
        $in = 'Link 👉 **https://buy.stripe.com/6oU7sNdxggRweXI9EL1B605**';
        $out = WhatsAppOutboundText::sanitize($in);

        $this->assertSame('Link 👉 https://buy.stripe.com/6oU7sNdxggRweXI9EL1B605', $out);
    }

    public function test_strips_bold_with_internal_whitespace(): void
    {
        $in = 'Ver ** https://wapify.me/demo ** acá';
        $out = WhatsAppOutboundText::sanitize($in);

        $this->assertStringContainsString('https://wapify.me/demo', $out);
        $this->assertStringNotContainsString('**', $out);
    }

    public function test_strips_single_asterisk_around_url(): void
    {
        $in = 'Demo: *https://wapify.me/demo*';
        $out = WhatsAppOutboundText::sanitize($in);

        $this->assertSame('Demo: https://wapify.me/demo', $out);
    }

    public function test_empty_string_unchanged(): void
    {
        $this->assertSame('', WhatsAppOutboundText::sanitize(''));
    }

    public function test_removes_unmatched_trailing_asterisk(): void
    {
        $in = 'Anotado, el evento **"Titulo"** sera el **24 de junio de 2026** *';
        $out = WhatsAppOutboundText::sanitize($in);

        $this->assertSame('Anotado, el evento *"Titulo"* sera el *24 de junio de 2026* ', $out);
    }

    public function test_collapses_triple_asterisks_into_double(): void
    {
        $in = 'Hola ***mundo***';
        $out = WhatsAppOutboundText::sanitize($in);

        $this->assertSame('Hola *mundo*', $out);
    }

    public function test_converts_markdown_table_to_whatsapp_friendly_lines(): void
    {
        $in = implode("\n", [
            'Detalle:',
            '| Factura | Fecha | Monto |',
            '|---|---|---|',
            '| 0005-0563 | 28/02/2026 | 20.909,09 ARS |',
            '| 0005-0482 | 30/01/2026 | 20.909,09 ARS |',
        ]);

        $out = WhatsAppOutboundText::sanitize($in);

        $this->assertStringNotContainsString('|---|---|---|', $out);
        $this->assertStringContainsString('• Factura: 0005-0563 | Fecha: 28/02/2026 | Monto: 20.909,09 ARS', $out);
        $this->assertStringContainsString('• Factura: 0005-0482 | Fecha: 30/01/2026 | Monto: 20.909,09 ARS', $out);
    }

    public function test_strips_bold_emphasis_from_currency_totals(): void
    {
        $in = 'Son 3 facturas pendientes por un total de **62.727,27 ARS**.';
        $out = WhatsAppOutboundText::sanitize($in);

        $this->assertSame('Son 3 facturas pendientes por un total de *62.727,27 ARS*.', $out);
    }
}
