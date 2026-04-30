<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignClassicEditorStoreTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    public function test_store_classic_editor_save_next_persists_campaign_and_redirects_with_campaign_id(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->post(route('campaigns.classic-editor.store'), [
            'intent' => 'save_next',
            'type' => 'sequences',
            'title' => 'Mi secuencia',
            'template_id' => 0,
            'subject' => 'Hola',
            'internal_title' => 'Paso 1',
            'body' => '<html><body>Contenido</body></html>',
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('campaigns', [
            'team_id' => $user->current_team_id,
            'name' => 'Mi secuencia',
            'type' => 'sequences',
        ]);

        $campaign = Campaign::withoutGlobalScopes()->where('team_id', $user->current_team_id)->where('name', 'Mi secuencia')->first();
        $this->assertNotNull($campaign);

        $message = Message::withoutGlobalScopes()->where('team_id', $user->current_team_id)->where('name', 'Paso 1')->first();
        $this->assertNotNull($message);

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('campaign_id='.$campaign->id, $location);
        $this->assertStringContainsString('type=sequences', $location);
        $this->assertStringContainsString('message_id='.$message->id, $location);

        $this->assertDatabaseHas('messages', [
            'team_id' => $user->current_team_id,
            'name' => 'Paso 1',
        ]);
    }

    public function test_store_classic_editor_save_updates_template_html_when_template_provided(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $template = Template::withoutGlobalScopes()->create([
            'name' => 'Editor test template',
            'team_id' => $user->current_team_id,
            'status_id' => 1,
            'gjs_data' => [
                'html' => '<table><tr><td>OLD</td></tr></table>',
                'components' => '[]',
                'styles' => '[]',
                'css' => '',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('campaigns.classic-editor.store'), [
            'intent' => 'save',
            'type' => 'broadcasts',
            'title' => 'Promo',
            'template_id' => $template->id,
            'subject' => 'Asunto',
            'internal_title' => 'Mail 1',
            'body' => '<table><tr><td>NEW</td></tr></table>',
        ]);

        $campaign = Campaign::withoutGlobalScopes()->where('team_id', $user->current_team_id)->where('name', 'Promo')->first();
        $this->assertNotNull($campaign);
        $response->assertRedirect(route('campaigns.show', $campaign));
        $response->assertSessionHas('success');

        $template->refresh();
        $this->assertStringContainsString('NEW', (string) ($template->gjs_data['html'] ?? ''));
    }

    public function test_store_classic_editor_requires_intent(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->post(route('campaigns.classic-editor.store'), [
            'type' => 'sequences',
        ]);

        $response->assertSessionHasErrors('intent');
    }

    public function test_store_classic_editor_save_redirects_to_campaign_show(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->post(route('campaigns.classic-editor.store'), [
            'intent' => 'save',
            'type' => 'sequences',
            'title' => 'Secuencia B',
            'template_id' => 0,
            'subject' => 'S',
            'internal_title' => 'M1',
        ]);

        $message = Message::withoutGlobalScopes()->where('team_id', $user->current_team_id)->where('name', 'M1')->first();
        $this->assertNotNull($message);

        $campaign = Campaign::withoutGlobalScopes()->where('team_id', $user->current_team_id)->where('name', 'Secuencia B')->first();
        $this->assertNotNull($campaign);

        $response->assertRedirect(route('campaigns.show', $campaign));
        $response->assertSessionHas('success');
    }
}
