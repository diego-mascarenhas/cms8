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

    public function test_message_create_without_templates_shows_standalone_mail_editor(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->currentTeam->id;

        $response = $this->actingAs($user)->get(route('message.create'));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertStringContainsString('id="message-store-form"', $html);
        $this->assertStringContainsString(e(__('app.message_form_template_none')), $html);
        $this->assertStringContainsString(e(__('app.message_form_template_optional_help')), $html);
        $this->assertStringContainsString('id="message-template-html-quill-editor"', $html);
        $this->assertStringContainsString('data-loaded-template-id="standalone"', $html);
        $this->assertStringContainsString('standalonePreviewUrl', $html);

        $this->assertSame(0, Template::withoutGlobalScopes()->where('team_id', $teamId)->count());
    }
}
