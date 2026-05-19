<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageSyncTemplateHtmlOpenEditorTest extends TestCase
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

    public function test_sync_persists_quill_html_then_redirects_to_visual_editor(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $staleComponents = json_encode([
            [
                'tagName' => 'div',
                'components' => [
                    [
                        'type' => 'text',
                        'content' => '<p>Stale only</p>',
                    ],
                ],
            ],
        ]);

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Gjs tpl',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<p>Old from db</p>',
                'css' => '',
                'components' => $staleComponents,
                'styles' => '[]',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('message.sync-template-html-open-editor'), [
            '_token' => csrf_token(),
            'template_id' => $template->id,
            'message_id' => null,
            'template_html' => '<h1>Welcome</h1><p>Line two</p><hr><h2>Meraki 2</h2>',
            'return_url' => route('message.create'),
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertMatchesRegularExpression('#/template/[^/]+/editor#', $location);

        $template->refresh();
        $this->assertStringContainsString('Meraki 2', (string) ($template->gjs_data['html'] ?? ''));
        $this->assertStringContainsString('email-wrapper', (string) ($template->gjs_data['components'] ?? ''));
        $this->assertStringContainsString('Meraki 2', (string) ($template->gjs_data['components'] ?? ''));
    }

    public function test_sync_with_message_id_persists_mail_html_on_message_not_template(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl base',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => ['html' => '<p>Template original</p>', 'css' => ''],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Email',
            'type_id' => 1,
            'text' => 'Alt',
            'team_id' => $teamId,
            'template_id' => $template->id,
            'mail_html' => '<p>Message body</p>',
            'status_id' => false,
        ]);

        $customHtml = '<p>Updated in composer</p>';

        $response = $this->actingAs($user)->post(route('message.sync-template-html-open-editor'), [
            '_token' => csrf_token(),
            'template_id' => $template->id,
            'message_id' => $message->id,
            'template_html' => $customHtml,
            'return_url' => route('message.edit', $message->id),
        ]);

        $response->assertRedirect();

        $message->refresh();
        $template->refresh();

        $this->assertSame($customHtml, (string) $message->mail_html);
        $this->assertSame('<p>Template original</p>', (string) ($template->gjs_data['html'] ?? ''));
    }

    public function test_sync_with_message_id_requires_email_message_type(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => ['html' => '<p>x</p>', 'css' => ''],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'WhatsApp',
            'type_id' => 2,
            'text' => 't',
            'team_id' => $teamId,
            'template_id' => $template->id,
            'status_id' => false,
        ]);

        $this->actingAs($user)->post(route('message.sync-template-html-open-editor'), [
            '_token' => csrf_token(),
            'template_id' => $template->id,
            'message_id' => $message->id,
            'template_html' => '<p>y</p>',
        ])->assertStatus(422);
    }

    public function test_sync_skips_persist_when_message_has_deliveries(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl locked',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => ['html' => '<p>locked</p>', 'css' => ''],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Email',
            'type_id' => 1,
            'text' => 't',
            'team_id' => $teamId,
            'template_id' => $template->id,
            'mail_html' => '<p>locked</p>',
            'status_id' => false,
        ]);

        $contact = Contact::factory()->create(['team_id' => $teamId]);
        $campaign = Campaign::factory()->create(['team_id' => $teamId]);
        $campaign->messages()->attach($message->id);

        MessageDelivery::query()->create([
            'team_id' => $teamId,
            'message_id' => $message->id,
            'contact_id' => $contact->id,
            'campaign_id' => $campaign->id,
            'status_id' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('message.sync-template-html-open-editor'), [
            '_token' => csrf_token(),
            'template_id' => $template->id,
            'message_id' => $message->id,
            'template_html' => '<p>should not save</p>',
            'return_url' => route('message.edit', $message->id),
        ]);

        $response->assertRedirect();
        $message->refresh();
        $template->refresh();
        $this->assertSame('<p>locked</p>', (string) $message->mail_html);
        $this->assertSame('<p>locked</p>', (string) ($template->gjs_data['html'] ?? ''));
    }
}
