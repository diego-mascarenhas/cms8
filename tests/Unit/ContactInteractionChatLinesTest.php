<?php

namespace Tests\Unit;

use App\Models\ContactInteraction;
use PHPUnit\Framework\TestCase;

class ContactInteractionChatLinesTest extends TestCase
{
    public function test_it_splits_a_whatsapp_export_into_messages(): void
    {
        $interaction = new ContactInteraction;
        $interaction->body = "[09/10/2026, 13:14:46] Diego: Hola Ale, cómo andás?\n[09/10/2026, 13:52:18] María Alejandra Arellano: Hola";

        $lines = $interaction->chatLines();

        $this->assertSame([
            [
                'at' => '09/10/2026, 13:14:46',
                'author' => 'Diego',
                'text' => 'Hola Ale, cómo andás?',
            ],
            [
                'at' => '09/10/2026, 13:52:18',
                'author' => 'María Alejandra Arellano',
                'text' => 'Hola',
            ],
        ], $lines);
    }

    public function test_plain_notes_stay_unparsed(): void
    {
        $interaction = new ContactInteraction;
        $interaction->body = "Llamamos y quedó en pensarlo.\nSegunda línea.";

        $this->assertSame([], $interaction->chatLines());
    }
}
