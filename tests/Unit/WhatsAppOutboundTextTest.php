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

        $this->assertSame('Anotado, el evento **"Titulo"** sera el **24 de junio de 2026** ', $out);
    }

    public function test_collapses_triple_asterisks_into_double(): void
    {
        $in = 'Hola ***mundo***';
        $out = WhatsAppOutboundText::sanitize($in);

        $this->assertSame('Hola **mundo**', $out);
    }
}
