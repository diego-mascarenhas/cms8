<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\MessageTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageFormDefaultTemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            MessageTypeSeeder::class,
        ]);
    }

    public function test_message_create_without_templates_shows_email_preview_and_creates_default(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->currentTeam->id;

        $response = $this->actingAs($user)->get(route('message.create'));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertStringContainsString('email-template-content-preview', $html);
        $this->assertStringContainsString(__('Contenido del correo'), $html);
        $this->assertStringContainsString('id="message-template-html-quill-editor"', $html);
        $this->assertStringContainsString('data-huma-merge-field-select', $html);
        $this->assertStringContainsString('{{name}}', $html);
        $this->assertStringNotContainsString(__('app.message_form_template_none'), $html);

        $this->assertSame(1, Template::withoutGlobalScopes()->where('team_id', $teamId)->count());
    }
}
