<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpWordPressMcpDocumentationTest extends TestCase
{
    public function test_wordpress_mcp_help_page_is_public(): void
    {
        $response = $this->get('/help/wordpress-mcp-cursor');

        $response->assertOk();
        $response->assertSee('mcp-adapter-default-server', false);
        $response->assertSee('@automattic/mcp-wordpress-remote', false);
        $response->assertSee('WP_API_PASSWORD', false);
        $response->assertSee('Application Password', false);
        $response->assertSee('wp_hash_password', false);
    }

    public function test_environment_variables_index_links_to_wordpress_mcp_help(): void
    {
        $response = $this->get('/help/environment-variables');

        $response->assertOk();
        $response->assertSee(route('help.wordpress-mcp-cursor'), false);
    }
}
