<?php

namespace Tests\Unit\Support;

use App\Support\LlmsTxt;
use PHPUnit\Framework\TestCase;

class LlmsTxtTest extends TestCase
{
    public function test_llms_txt_names_the_admin_and_the_public_pages(): void
    {
        $body = LlmsTxt::body('https://admin.idoneo.dev');

        $this->assertStringContainsString('# IDONEO Admin', $body);
        $this->assertStringContainsString('https://admin.idoneo.dev/cms', $body);
        $this->assertStringContainsString('https://admin.idoneo.dev/login', $body);
        $this->assertStringContainsString('https://pedimosfacil.com', $body);
        $this->assertStringContainsString('https://growhit.net', $body);
        $this->assertStringContainsString('https://humano.website', $body);
    }

    public function test_json_ld_describes_the_admin_application(): void
    {
        $data = LlmsTxt::jsonLd('https://admin.idoneo.dev');

        $this->assertSame('SoftwareApplication', $data['@type']);
        $this->assertSame('IDONEO Admin', $data['name']);
        $this->assertSame('https://admin.idoneo.dev', $data['url']);
        $this->assertSame('https://idoneo.dev', $data['publisher']['url']);
        $this->assertStringNotContainsString('<', LlmsTxt::jsonScript('https://admin.idoneo.dev'));
    }
}
