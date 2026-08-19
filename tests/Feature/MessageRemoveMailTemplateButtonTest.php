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

class MessageRemoveMailTemplateButtonTest extends TestCase
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

    public function test_message_create_with_template_shows_template_select_and_no_remove_mail_template_link(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl remove btn',
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
            'name' => 'My broadcast name',
        ]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('id="template_id"', $html);
        $this->assertDoesNotMatchRegularExpression('/<a[^>]+href="[^"]*remove_mail_template=1[^"]*"/', $html);
    }

    public function test_message_edit_with_template_shows_template_select_and_no_remove_mail_template_link(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl edit remove',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<html><body><p>x</p></body></html>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Edit remove tpl',
            'type_id' => 1,
            'text' => 'Alt body text here',
            'team_id' => $teamId,
            'template_id' => $template->id,
            'status_id' => false,
        ]);

        $response = $this->actingAs($user)->get(route('message.edit', $message->id));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('id="template_id"', $html);
        $this->assertDoesNotMatchRegularExpression('/<a[^>]+href="[^"]*remove_mail_template=1[^"]*"/', $html);
    }

    /**
     * Dropping the template no longer leaves the message without a body: the template-bound preview
     * is swapped for a standalone editor, and the select comes back so another one can be picked.
     */
    public function test_message_edit_remove_mail_template_falls_back_to_the_standalone_editor(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Tpl compose off',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<html><body><p>y</p></body></html>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $message = Message::withoutGlobalScopes()->create([
            'name' => 'Compose without tpl',
            'type_id' => 1,
            'text' => 'Alt body text here too',
            'team_id' => $teamId,
            'template_id' => $template->id,
            'status_id' => false,
        ]);

        $response = $this->actingAs($user)->get(route('message.edit', [
            'id' => $message->id,
            'remove_mail_template' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('id="template_id"', false);
        $response->assertSee('name="template_html"', false);
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]+href="[^"]*remove_mail_template=1[^"]*"/',
            (string) $response->getContent(),
        );
    }
}
