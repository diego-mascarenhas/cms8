<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\MessageDelivery;
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
        $this->assertStringContainsString('id="message-store-form"', $html);
        $this->assertStringContainsString("getElementById('message-store-form')", $html);
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

    public function test_message_create_legacy_form_contact_status_select_reinitializes_select2_with_allow_clear(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->get(route('message.create', ['legacy_form' => 1]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/\$\([\'"]#contact_status_id[\'"]\)[\s\S]*?select2\s*\(\s*[\'"]destroy[\'"]/s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/\$\([\'"]#contact_status_id[\'"]\)[\s\S]*?allowClear:\s*true/s',
            $html,
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

    public function test_message_store_update_succeeds_when_type_id_omitted_with_template(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Store tpl',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<html><body><p>Hi</p></body></html>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Newsletter Demo',
            'type_id' => 1,
            'text' => 'Hola {{name}}, bienvenido a nuestra plataforma.',
            'team_id' => $teamId,
            'template_id' => $template->id,
            'status_id' => false,
        ]);

        $response = $this->actingAs($user)->post(route('message.store'), [
            'id' => (string) $message->id,
            'template_id' => (string) $template->id,
            'name' => 'Newsletter Demo',
            'category_id' => '',
            'contact_status_id' => null,
            'text' => 'Hola {{name}}, bienvenido a nuestra plataforma.',
            'min_hours_between_emails' => '2',
            'time_unit' => 'days',
            'send_allowed_weekdays' => ['1', '2', '3', '4', '5', '6', '7'],
            'send_window_start' => null,
            'send_window_end' => null,
            'status_id' => '0',
            'show_unsubscribe' => '1',
            'enable_open_tracking' => '1',
            'enable_click_tracking' => '1',
        ]);

        $response->assertRedirect(route('message.index'));

        $message->refresh();
        $this->assertSame(1, $message->type_id);
        $this->assertSame($template->id, $message->template_id);
    }

    public function test_message_store_updates_template_html_when_type_mail_and_template(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Mail html tpl',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<html><body><p>Original</p></body></html>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $customHtml = '<html><body><p>Custom override</p></body></html>';

        $response = $this->actingAs($user)->post(route('message.store'), [
            'id' => '',
            'name' => 'Campaign with custom HTML',
            'text' => 'Plain text summary for the message record',
            'type_id' => 1,
            'template_id' => (string) $template->id,
            'template_html' => $customHtml,
            'category_id' => '',
            'contact_status_id' => '',
            'min_hours_between_emails' => 48,
            'send_allowed_weekdays' => [1, 2, 3, 4, 5],
        ]);

        $response->assertRedirect(route('message.index'));

        $message = Message::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('name', 'Campaign with custom HTML')
            ->first();

        $this->assertNotNull($message);
        $this->assertSame($customHtml, $template->fresh()->gjs_data['html']);
    }

    public function test_message_store_does_not_update_template_html_when_message_has_deliveries(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $originalHtml = '<html><body><p>Locked</p></body></html>';
        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl locked',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => $originalHtml,
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Sent once',
            'type_id' => 1,
            'text' => 'Body',
            'team_id' => $teamId,
            'template_id' => $template->id,
            'status_id' => false,
        ]);

        $contact = \App\Models\Contact::factory()->create([
            'team_id' => $teamId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        MessageDelivery::create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'campaign_id' => null,
            'status_id' => 1,
            'sent_at' => now(),
        ]);

        $tamperedHtml = '<html><body><p>Tampered</p></body></html>';

        $response = $this->actingAs($user)->post(route('message.store'), [
            'id' => (string) $message->id,
            'template_id' => (string) $template->id,
            'name' => 'Sent once',
            'text' => 'Body',
            'type_id' => 1,
            'template_html' => $tamperedHtml,
            'category_id' => '',
            'contact_status_id' => '',
            'min_hours_between_emails' => 48,
            'send_allowed_weekdays' => [1, 2, 3, 4, 5],
        ]);

        $response->assertRedirect(route('message.index'));
        $this->assertSame($originalHtml, $template->fresh()->gjs_data['html']);
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
        $this->assertStringContainsString('id="message-template-html-quill-editor"', $html);
        $this->assertStringContainsString('name="template_html"', $html);
        $this->assertStringContainsString('id="message-template-html-initial-json"', $html);
        $this->assertStringContainsString('\u003Cp\u003EHi\u003C\\/p\u003E', $html);
        $this->assertStringContainsString('id="template_id"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]+type=["\']hidden["\'][^>]+name=["\']type_id["\'][^>]+value=["\']1["\']/i', $html);
        $this->assertMatchesRegularExpression('/<select[^>]+id=["\']type_id["\'][^>]*disabled/si', $html);
        $this->assertStringContainsString('form="message-email-template-duplicate-form"', $html);
        $this->assertStringContainsString('id="message-email-template-duplicate-form"', $html);
        $this->assertStringContainsString('id="message-email-template-duplicate-modal"', $html);
        $this->assertStringContainsString('name="duplicate_template_name"', $html);
        $this->assertStringContainsString('id="email-test-send-modal-draft-'.$template->id.'"', $html);
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
        $response->assertSee('data-email-test-send-recipients', false);
        $response->assertSee('openEmailTestSendModal', false);
        $html = $response->getContent() ?? '';
        $this->assertStringContainsString('form="message-email-template-duplicate-form"', $html);
        $this->assertStringContainsString('id="message-email-template-duplicate-form"', $html);
        $this->assertStringContainsString('id="message-email-template-duplicate-modal"', $html);
        $this->assertStringContainsString('name="duplicate_template_name"', $html);
    }
}
