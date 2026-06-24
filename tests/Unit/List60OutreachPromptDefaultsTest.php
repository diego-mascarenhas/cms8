<?php

namespace Tests\Unit;

use App\Support\List60OutreachPromptDefaults;
use Tests\TestCase;

class List60OutreachPromptDefaultsTest extends TestCase
{
    public function test_first_contact_instruction_references_isra_bravo_as_copywriter(): void
    {
        $instruction = List60OutreachPromptDefaults::firstContactInstruction();

        $this->assertStringContainsString('Isra Bravo', $instruction);
        $this->assertStringContainsString('cartas de venta', $instruction);
        $this->assertStringContainsString('300 palabras', $instruction);
        $this->assertStringContainsString('honesta', $instruction);
    }

    public function test_follow_up_instruction_references_conversational_style(): void
    {
        $instruction = List60OutreachPromptDefaults::followUpInstruction();

        $this->assertStringContainsString('Isra Bravo', $instruction);
        $this->assertStringContainsString('conversacionales', $instruction);
    }
}
