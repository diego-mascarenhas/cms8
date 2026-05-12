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
        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/<select[^>]+id=["\']type_id["\'][^>]+name=["\']type_id["\']/si',
            $html,
        );
        $response->assertSee(__('Message sending schedule'), false);
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

    public function test_message_create_legacy_form_renders_contact_status_all_placeholder_option(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->get(route('message.create', ['legacy_form' => 1]));

        $response->assertOk();
        $response->assertSee(e(__('app.message_form_contact_status_all')), false);
        $response->assertSee('id="contact_status_id"', false);
        $this->assertMatchesRegularExpression(
            '/id="contact_status_id"[^>]*>[\s\S]*?<option value="">'.preg_quote(e(__('app.message_form_contact_status_all')), '/').'<\/option>/',
            $response->getContent(),
        );
    }

    public function test_message_store_persists_null_contact_status_when_empty(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $response = $this->actingAs($user)->post(route('message.store'), [
            'id' => '',
            'name' => 'Broadcast all statuses',
            'text' => 'Alt text for the campaign body',
            'type_id' => 1,
            'category_id' => '',
            'contact_status_id' => '',
            'min_hours_between_emails' => 48,
            'send_allowed_weekdays' => [1, 2, 3, 4, 5],
        ]);

        $response->assertRedirect(route('message.index'));

        $message = Message::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('name', 'Broadcast all statuses')
            ->first();

        $this->assertNotNull($message);
        $this->assertNull($message->contact_status_id);
    }

    public function test_message_store_persists_null_template_id_when_empty(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $response = $this->actingAs($user)->post(route('message.store'), [
            'id' => '',
            'name' => 'Plain text campaign',
            'text' => 'Hello, this is the full email body without a template.',
            'type_id' => 1,
            'category_id' => '',
            'contact_status_id' => '',
            'template_id' => '',
            'min_hours_between_emails' => 48,
            'send_allowed_weekdays' => [1, 2, 3, 4, 5],
        ]);

        $response->assertRedirect(route('message.index'));

        $message = Message::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('name', 'Plain text campaign')
            ->first();

        $this->assertNotNull($message);
        $this->assertNull($message->template_id);
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
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/<input[^>]+type=["\']hidden["\'][^>]+name=["\']type_id["\'][^>]+value=["\']1["\']/i', $html);
        $this->assertMatchesRegularExpression('/<select[^>]+id=["\']type_id["\'][^>]*disabled/si', $html);
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
