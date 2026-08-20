<?php

namespace Tests\Unit;

use App\Services\DefaultAssistantFlowPromptsService;
use PHPUnit\Framework\TestCase;

/**
 * Guards the behaviours the assistant copy is supposed to enforce: finish the sale,
 * never confirm without a tool result, and stay short.
 */
class AssistantPromptCopyTest extends TestCase
{
    private function flowInstruction(string $sectionKey): string
    {
        foreach (DefaultAssistantFlowPromptsService::definitions() as $def)
        {
            if ($def['section_key'] === $sectionKey)
            {
                return $def['prompt_instruction'];
            }
        }

        $this->fail("No default flow prompt for section key {$sectionKey}.");
    }

    public function test_sales_funnel_walks_from_capture_to_a_closed_order(): void
    {
        $instruction = $this->flowInstruction('assistant_embudo');

        $this->assertStringContainsString('search_contacts', $instruction);
        $this->assertStringContainsString('list_product_catalog', $instruction);
        $this->assertStringContainsString('search_products', $instruction);
        $this->assertStringContainsString('add_to_whatsapp_cart', $instruction);
        $this->assertStringContainsString('finalizar', $instruction);
        $this->assertStringContainsString('Escalera', $instruction);
    }

    public function test_catalog_flow_walks_the_customer_to_a_closed_order(): void
    {
        $instruction = $this->flowInstruction('assistant_catalogo');

        $this->assertStringContainsString('list_product_catalog', $instruction);
        $this->assertStringContainsString('search_products', $instruction);
        $this->assertStringContainsString('add_to_whatsapp_cart', $instruction);
        $this->assertStringContainsString('finalizar', $instruction);
    }

    public function test_every_default_flow_forbids_making_data_up(): void
    {
        foreach (DefaultAssistantFlowPromptsService::definitions() as $def)
        {
            if (str_starts_with($def['section_key'], 'primer_contacto') || $def['section_key'] === 'seguimiento')
            {
                $this->assertStringContainsString('No inventes', $def['prompt_instruction']);

                continue;
            }

            $this->assertStringContainsString(
                'no inventes',
                $def['prompt_instruction'],
                "Flow {$def['section_key']} does not carry the anti-invention rule.",
            );
        }
    }

    public function test_calendar_flow_offers_concrete_slots(): void
    {
        $instruction = $this->flowInstruction('assistant_citas');

        $this->assertStringContainsString('check_calendar_availability', $instruction);
        $this->assertStringContainsString('huecos concretos', $instruction);
        $this->assertStringContainsString('quien la pide', $instruction);
        $this->assertStringContainsString('email', $instruction);
        $this->assertStringContainsString('nombre, apellido y email', $instruction);
        $this->assertStringContainsString('guest_contact_ids', $instruction);
        $this->assertStringContainsString('create_contact', $instruction);
        $this->assertStringContainsString('update_contact', $instruction);
    }

    public function test_task_flow_requires_a_successful_tool_call_before_confirming(): void
    {
        $instruction = $this->flowInstruction('assistant_tareas');

        $this->assertStringContainsString('search_tasks', $instruction);
        $this->assertStringContainsString('update_task_status', $instruction);
    }
}
