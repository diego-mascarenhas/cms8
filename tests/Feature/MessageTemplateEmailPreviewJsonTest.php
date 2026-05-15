<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTemplateEmailPreviewJsonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);
    }

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    public function test_template_email_preview_requires_authentication(): void
    {
        $response = $this->getJson(route('message.template-email-preview', [
            'template_id' => 1,
        ]));

        $response->assertUnauthorized();
    }

    public function test_template_email_preview_validates_template_id(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->getJson(route('message.template-email-preview', []));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['template_id']);
    }

    public function test_template_email_preview_returns_html_and_preview_for_team_template(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl json preview',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<html><body><p>Preview body</p></body></html>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $response = $this->actingAs($user)->getJson(route('message.template-email-preview', [
            'template_id' => $template->id,
            'return_url' => '/message/list',
        ]));

        $response->assertOk();
        $response->assertJsonStructure(['html', 'preview_html', 'duplicate_action_url']);
        $html = $response->json('html');
        $this->assertStringContainsString('email-template-content-preview', $html);
        $this->assertStringContainsString('name="template_html"', $html);
        $this->assertStringContainsString('id="message-template-html-quill-editor"', $html);
        $this->assertStringContainsString('data-huma-open-visual-editor', $html);
        $this->assertStringContainsString('Preview body', $html);
        $this->assertStringContainsString('Preview body', $response->json('preview_html'));
        $this->assertStringContainsString('/template/', $response->json('duplicate_action_url'));
    }

    public function test_template_email_preview_accepts_message_id_for_return_url(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl with message return',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<html><body>x</body></html>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'M',
            'type_id' => 1,
            'text' => 't',
            'team_id' => $teamId,
            'template_id' => $template->id,
            'status_id' => false,
        ]);

        $response = $this->actingAs($user)->getJson(route('message.template-email-preview', [
            'template_id' => $template->id,
            'message_id' => $message->id,
        ]));

        $response->assertOk();
        $html = $response->json('html');
        $modalDomId = 'id="email-test-send-modal-'.$message->id.'"';
        $this->assertStringContainsString($modalDomId, $html);
        $this->assertSame(1, substr_count($html, $modalDomId), 'Preview fragment must include a single test-send modal root id.');
    }
}
