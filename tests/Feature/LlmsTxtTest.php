<?php

namespace Tests\Feature;

use Tests\TestCase;

class LlmsTxtTest extends TestCase
{
    public function test_llms_txt_is_public_plain_text(): void
    {
        config(['app.url' => 'https://admin.idoneo.dev']);

        $response = $this->get('/llms.txt');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('content-type'));
        $response->assertSee('# IDONEO Admin', false);
        $response->assertSee('https://admin.idoneo.dev/cms', false);
    }

    public function test_login_and_cms_landing_expose_the_admin_entity(): void
    {
        config(['app.url' => 'https://admin.idoneo.dev']);

        $this->get('/login')
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('IDONEO Admin', false);

        $this->get('/cms')
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('https://admin.idoneo.dev', false);
    }
}
