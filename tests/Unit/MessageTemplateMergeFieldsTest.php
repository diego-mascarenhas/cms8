<?php

namespace Tests\Unit;

use App\Support\MessageTemplateMergeFields;
use Tests\TestCase;

class MessageTemplateMergeFieldsTest extends TestCase
{
    public function test_replace_substitutes_all_english_contact_tokens(): void
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

    public function test_replace_substitutes_all_spanish_contact_tokens(): void
    {
        $contact = (object) [
            'name' => 'Laura',
            'surname' => 'García',
            'email' => 'laura@example.com',
            'phone' => '34600111222',
        ];

        $html = '<p>Hola {{nombre}} {{apellido}} ({{nombre_completo}} / {{nombre_contacto}}). Email: {{email}}. Tel: {{telefono}}.</p>';

        $result = MessageTemplateMergeFields::replace($html, $contact);

        $this->assertSame(
            '<p>Hola Laura García (Laura García / Laura García). Email: laura@example.com. Tel: 34600111222.</p>',
            $result,
        );
    }

    public function test_replace_with_sample_uses_sample_contact(): void
    {
        $result = MessageTemplateMergeFields::replaceWithSample('Hola {{nombre}} {{apellido}}');

        $this->assertSame('Hola John Doe', $result);
    }

    public function test_for_ui_uses_spanish_tokens_when_locale_is_spanish(): void
    {
        $tokens = array_column(MessageTemplateMergeFields::forUi('es'), 'token');

        $this->assertContains('{{nombre}}', $tokens);
        $this->assertContains('{{nombre_completo}}', $tokens);
        $this->assertContains('{{telefono}}', $tokens);
        $this->assertNotContains('{{name}}', $tokens);
    }

    public function test_for_ui_uses_english_tokens_when_locale_is_english(): void
    {
        $tokens = array_column(MessageTemplateMergeFields::forUi('en'), 'token');

        $this->assertContains('{{name}}', $tokens);
        $this->assertContains('{{full_name}}', $tokens);
        $this->assertContains('{{phone}}', $tokens);
        $this->assertNotContains('{{nombre}}', $tokens);
    }
}
