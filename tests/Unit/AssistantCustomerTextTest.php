<?php

namespace Tests\Unit;

use App\Support\AssistantCustomerText;
use PHPUnit\Framework\TestCase;

class AssistantCustomerTextTest extends TestCase
{
    public function test_strips_echoed_flow_committed_marker(): void
    {
        $raw = 'FLOW_COMMITTED:{"routing_key":"chat:assistantpresupuesto","label":"Pedido de presupuesto"}';

        $this->assertSame('', AssistantCustomerText::stripMachineMarkers($raw));
        $this->assertSame('chat:assistantpresupuesto', AssistantCustomerText::parseCommittedPayload($raw)['routing_key'] ?? null);
        $this->assertStringContainsString(
            'te armo el pedido',
            AssistantCustomerText::afterCommitFallback(AssistantCustomerText::parseCommittedPayload($raw)),
        );
    }

    public function test_keeps_natural_text_around_the_marker(): void
    {
        $raw = "Dale, lo vemos.\nFLOW_COMMITTED:{\"routing_key\":\"chat:assistant_presupuesto\",\"label\":\"Pedido de presupuesto\"}\n¿Para cuándo?";

        $this->assertSame("Dale, lo vemos.\n¿Para cuándo?", AssistantCustomerText::stripMachineMarkers($raw));
    }

    public function test_strips_leaked_calendar_tool_json_and_asks_for_a_time(): void
    {
        $raw = "Voy a consultar la disponibilidad de mañana en la agenda. Dame un momento.\n\n```json\n{\"tool\": \"check_calendar_availability\", \"date\": \"2025-07-16\"}\n```";

        $this->assertSame('check_calendar_availability', AssistantCustomerText::leakedToolName($raw));
        $this->assertTrue(AssistantCustomerText::looksLikeToolStall(AssistantCustomerText::stripMachineMarkers($raw)));
        $this->assertStringNotContainsString('check_calendar_availability', AssistantCustomerText::stripMachineMarkers($raw));
        $this->assertStringContainsString(
            'horario',
            AssistantCustomerText::afterLeakedToolFallback(AssistantCustomerText::leakedToolName($raw)),
        );
    }

    public function test_keeps_natural_text_when_leaked_json_is_not_a_stall(): void
    {
        $raw = "Tenemos hueco a las 10.\n{\"tool\": \"check_calendar_availability\", \"start\": \"2026-09-01 10:00:00\"}";

        $this->assertSame('Tenemos hueco a las 10.', AssistantCustomerText::stripMachineMarkers($raw));
        $this->assertFalse(AssistantCustomerText::looksLikeToolStall(AssistantCustomerText::stripMachineMarkers($raw)));
    }
}
