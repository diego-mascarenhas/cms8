<?php

namespace Tests\Feature;

use Tests\TestCase;

class StackPageTest extends TestCase
{
    public function test_stack_page_is_publicly_accessible(): void
    {
        $response = $this->get('/stack');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertSee('Stack técnico Humano', false);
        $response->assertSee('PostgreSQL', false);
        $response->assertSee('compatibilidad', false);
    }
}
