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
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MessageSyncTemplateHtmlTest extends TestCase
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

    private function userWithTemplateEdit(): User
    {
        Permission::firstOrCreate(['name' => 'template.edit', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->givePermissionTo('template.edit');

        return $user->fresh();
    }

    public function test_sync_template_html_updates_template_and_returns_to_message_form(): void
    {
        $user = $this->userWithTemplateEdit();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl to update',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => ['html' => '<p>Old</p>', 'css' => ''],
        ]);

        $customHtml = '<h1>Updated from Quill</h1>';

        $response = $this->actingAs($user)->post(route('message.sync-template-html'), [
            '_token' => csrf_token(),
            'template_id' => $template->id,
            'message_id' => null,
            'template_html' => $customHtml,
            'return_url' => route('message.create'),
        ]);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/message/create', $location);
        $this->assertStringContainsString('template_id='.$template->id, $location);
        $this->assertStringNotContainsString('/template/', $location);

        $template->refresh();
        $this->assertStringContainsString('Updated from Quill', (string) ($template->gjs_data['html'] ?? ''));
    }

    public function test_sync_template_html_allowed_for_authenticated_user_without_template_permission(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user = $user->fresh();

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl from composer',
            'team_id' => (int) $team->id,
            'status_id' => 1,
            'gjs_data' => ['html' => '<p>Old</p>'],
        ]);

        $response = $this->actingAs($user)->post(route('message.sync-template-html'), [
            '_token' => csrf_token(),
            'template_id' => $template->id,
            'template_html' => '<p>Updated without template permission</p>',
            'return_url' => route('message.create'),
        ]);

        $response->assertRedirect();
        $template->refresh();
        $this->assertSame('<p>Updated without template permission</p>', (string) ($template->gjs_data['html'] ?? ''));
    }

    public function test_sync_template_html_blocked_when_message_has_deliveries(): void
    {
        $user = $this->userWithTemplateEdit();
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

        $response = $this->from(route('message.edit', $message->id))
            ->actingAs($user)
            ->post(route('message.sync-template-html'), [
                '_token' => csrf_token(),
                'template_id' => $template->id,
                'message_id' => $message->id,
                'template_html' => '<p>should not save</p>',
                'return_url' => route('message.edit', $message->id),
            ]);

        $response->assertRedirect(route('message.edit', $message->id));
        $template->refresh();
        $this->assertSame('<p>locked</p>', (string) ($template->gjs_data['html'] ?? ''));
    }

    public function test_preview_bundle_shows_update_template_button(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user = $user->fresh();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Preview tpl',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => ['html' => '<p>Hi</p>', 'css' => ''],
        ]);

        $response = $this->actingAs($user)->getJson(route('message.template-email-preview', [
            'template_id' => $template->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('data-huma-update-template="1"', (string) $response->json('html'));
        $this->assertStringContainsString(__('app.email_template_update_button'), (string) $response->json('html'));
    }
}
