<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingEmbedDemoTest extends TestCase
{
    public function test_landing_page_contains_embed_placeholders(): void
    {
        $this->get('/landing')
            ->assertOk()
            ->assertSee('data-humano-widget="calendar"', false)
            ->assertSee('humano-widgets.js', false);
    }

    public function test_embed_demo_calendar_returns_json(): void
    {
        $this->getJson('/api/embed/demo/calendar')
            ->assertOk()
            ->assertJsonStructure([
                'title',
                'slots' => [
                    '*' => ['id', 'label', 'available'],
                ],
            ]);
    }

    public function test_embed_demo_assistant_returns_reply(): void
    {
        $this->postJson('/api/embed/demo/assistant', ['message' => 'hello'])
            ->assertOk()
            ->assertJsonFragment(['demo' => true])
            ->assertJsonStructure(['reply', 'demo']);
    }

    public function test_embed_demo_assistant_validates_message(): void
    {
        $this->postJson('/api/embed/demo/assistant', [])
            ->assertStatus(422);
    }
}
