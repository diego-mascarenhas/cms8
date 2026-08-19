<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    /**
     * `/` forwards to whichever landing is configured as the public home, so follow the redirect
     * instead of asserting a bare 200 that only held when the root rendered the page itself.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->followingRedirects()->get('/');

        $response->assertStatus(200);
    }
}
