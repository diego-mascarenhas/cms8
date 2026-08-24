<?php

namespace Tests\Unit;

use App\Support\List60OutreachPromptDefaults;
use Tests\TestCase;

class List60OutreachPromptDefaultsTest extends TestCase
{
    public function test_first_contact_instruction_asks_for_a_short_human_whatsapp(): void
    {
        $instruction = List60OutreachPromptDefaults::firstContactInstruction();

        $this->assertStringContainsString('corto, humano', $instruction);
        $this->assertStringContainsString('sin vender', $instruction);
        $this->assertStringContainsString('No inventes', $instruction);
    }

    public function test_follow_up_instruction_asks_for_a_quick_human_nudge(): void
    {
        $instruction = List60OutreachPromptDefaults::followUpInstruction();

        $this->assertStringContainsString('toque rápido', $instruction);
        $this->assertStringContainsString('no el pitch', $instruction);
        $this->assertStringContainsString('No inventes', $instruction);
    }

    public function test_whatsapp_brevity_rules_cap_length_and_forbid_a_pitch(): void
    {
        $rules = List60OutreachPromptDefaults::whatsappBrevityRules();

        $this->assertStringContainsString('1 o 2 frases', $rules);
        $this->assertStringContainsString('220 caracteres', $rules);
        $this->assertStringContainsString('Nada de pitch', $rules);
    }

    public function test_alta_instruction_classifies_inbox_and_asks_for_a_suggested_reply(): void
    {
        $instruction = List60OutreachPromptDefaults::altaInstruction();

        $this->assertStringContainsString('alta desde el inbox', $instruction);
        $this->assertStringContainsString('already_on_list', $instruction);
        $this->assertStringContainsString('archivado', $instruction);
        $this->assertStringContainsString('suggested_message', $instruction);
        $this->assertStringContainsString('respuesta acertada', $instruction);
        $this->assertStringContainsString('suggested_responsible_id', $instruction);
        $this->assertStringContainsString('1 o 2 frases', $instruction);
    }
}
