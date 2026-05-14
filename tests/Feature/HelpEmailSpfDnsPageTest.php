<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpEmailSpfDnsPageTest extends TestCase
{
    public function test_help_email_spf_dns_page_is_public_and_ok(): void
    {
        $response = $this->get(route('help.email-spf-dns'));

        $response->assertOk();
        $response->assertSee(\App\Helpers\DnsHelper::REQUIRED_REVISION_ALPHA_SPF_TXT, false);
    }
}
