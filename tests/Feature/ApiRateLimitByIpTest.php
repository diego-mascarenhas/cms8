<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRateLimitByIpTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requests_are_limited_per_ip_and_webhooks_are_excluded(): void
    {
        config(['app.api_rate_limit_per_minute' => 2]);

        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/user')->assertStatus(429);

        $this->postJson('/api/wordpress/webhook/1', [])
            ->assertNotFound();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->getJson('/api/user')
            ->assertUnauthorized();
    }
}
