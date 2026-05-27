<?php

namespace Tests\Unit;

use App\Support\MessageTemplateMergeFields;
use Tests\TestCase;

class MessageTemplateMergeFieldsTest extends TestCase
{
    public function test_replace_substitutes_all_contact_tokens(): void
    {
        $contact = (object) [
            'name' => 'Laura',
            'surname' => 'García',
            'email' => 'laura@example.com',
            'phone' => '34600111222',
        ];

        $html = '<p>Hola {{name}} {{surname}} ({{full_name}} / {{contact_name}}). Email: {{email}}. Tel: {{phone}}.</p>';

        $result = MessageTemplateMergeFields::replace($html, $contact);

        $this->assertSame(
            '<p>Hola Laura García (Laura García / Laura García). Email: laura@example.com. Tel: 34600111222.</p>',
            $result,
        );
    }

    public function test_for_ui_includes_standard_tokens(): void
    {
        $tokens = array_column(MessageTemplateMergeFields::forUi(), 'token');

        $this->assertContains('{{name}}', $tokens);
        $this->assertContains('{{full_name}}', $tokens);
        $this->assertContains('{{phone}}', $tokens);
    }
}
