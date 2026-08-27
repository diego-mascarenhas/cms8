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
}
