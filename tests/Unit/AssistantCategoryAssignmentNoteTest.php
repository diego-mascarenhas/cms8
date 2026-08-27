<?php

namespace Tests\Unit;

use App\Helpers\AssistantCategoryAssignmentNote;
use Tests\TestCase;

class AssistantCategoryAssignmentNoteTest extends TestCase
{
    public function test_formats_a_single_category_in_spanish(): void
    {
        $this->assertSame(
            'Contacto asignado a la categoría: ESQUINA',
            AssistantCategoryAssignmentNote::inboxBody(['ESQUINA']),
        );
    }

    public function test_extracts_the_category_from_the_english_tool_result(): void
    {
        $text = 'Contact Diego (id: 122) assigned to category: ESQUINA. Do not mention the tag name to the customer.';

        $this->assertSame(['ESQUINA'], AssistantCategoryAssignmentNote::extractCategoryNames($text));
        $this->assertSame('', AssistantCategoryAssignmentNote::stripFromCustomerText($text));
    }

    public function test_keeps_the_customer_reply_and_drops_the_assignment_echo(): void
    {
        $text = "Hola, ¿en qué te puedo ayudar?\n\nContact Diego (id: 122) assigned to category: VW. Do not mention the tag name to the customer.";

        $this->assertSame('Hola, ¿en qué te puedo ayudar?', AssistantCategoryAssignmentNote::stripFromCustomerText($text));
        $this->assertSame(['VW'], AssistantCategoryAssignmentNote::extractCategoryNames($text));
    }
}
