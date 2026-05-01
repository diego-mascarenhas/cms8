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

class MessageCreateTemplateFlowTest extends TestCase
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

    public function test_message_create_redirects_to_template_gallery_when_no_template_id(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->get(route('message.create'));

        $response->assertRedirect(route('campaigns.templates.select', [
            'type' => 'messages',
            'title' => '',
        ]));
    }

    public function test_message_create_legacy_form_skips_gallery(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->get(route('message.create', ['legacy_form' => 1]));

        $response->assertOk();
    }

    public function test_message_create_legacy_form_defaults_minimum_interval_unit_to_days(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->get(route('message.create', ['legacy_form' => 1]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/<option[^>]+value=["\']days["\'][^>]*selected/s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<input[^>]+id=["\']min_hours_between_emails["\'][^>]+value=["\']2["\']/s',
            $html,
        );
    }

    public function test_message_create_with_template_shows_email_content_preview(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Preview tpl',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<html><body><p>Hi</p></body></html>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('message.create', [
            'template_id' => $template->id,
            'name' => 'My broadcast',
        ]));

        $response->assertOk();
        $response->assertSee(__('Contenido del correo'), false);
        $response->assertSee(e($template->name), false);
    }

    public function test_message_show_does_not_render_email_content_preview_card_for_mailer_with_template(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Show tpl',
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
            'name' => 'Show me',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'template_id' => $template->id,
            'status_id' => false,
        ]);

        $response = $this->actingAs($user)->get(route('message.show', $message->id));

        $response->assertOk();
        $response->assertDontSee(__('Contenido del correo'));
    }

    public function test_message_edit_renders_email_test_send_modal_when_mailer_template_preview_shown(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Edit tpl',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<html><body><p>Edit</p></body></html>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Edit me',
            'type_id' => 1,
            'text' => 'Hi',
            'team_id' => $teamId,
            'template_id' => $template->id,
            'status_id' => false,
        ]);

        $response = $this->actingAs($user)->get(route('message.edit', $message->id));

        $response->assertOk();
        $response->assertSee('id="email-test-send-modal-'.$message->id.'"', false);
        $response->assertSee('openEmailTestSendModal', false);
    }
}
