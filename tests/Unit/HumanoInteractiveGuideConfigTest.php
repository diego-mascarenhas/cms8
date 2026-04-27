<?php

namespace Tests\Unit;

use Tests\TestCase;

class HumanoInteractiveGuideConfigTest extends TestCase
{
    public function test_humano_interactive_guide_instructions_are_non_empty(): void
    {
        $text = (string) config('humano_interactive_guide.instructions');
        $this->assertNotSame('', trim($text));
        $this->assertStringContainsString('Humano', $text);
        $this->assertStringContainsString('/register', $text);
        $this->assertStringContainsString('/registration/onboarding/qr', $text);
        $this->assertNotSame('', trim((string) config('humano_interactive_guide.web_help_hint')));
        $this->assertStringContainsString('/register', (string) config('humano_interactive_guide.web_help_hint'));
        $this->assertNotSame('', trim((string) config('humano_interactive_guide.whatsapp_help_hint')));
    }
}
