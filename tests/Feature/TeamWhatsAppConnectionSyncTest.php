<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TeamWhatsAppChatPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamWhatsAppConnectionSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_links_gateway_number_when_team_has_no_whatsapp_from(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/status*' => Http::response(['status' => 'connected', 'number' => '5491167284492'], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->assertNull($team->fresh()->getWhatsAppFrom());

        $presentation = TeamWhatsAppChatPresentation::resolveForTeam($team->fresh());

        $this->assertTrue($presentation['teamWhatsAppIsConnected']);
        $this->assertSame('5491167284492', $team->fresh()->getWhatsAppFrom());
    }

    public function test_whatsapp_status_auto_links_and_reports_team_connected(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/status*' => Http::response(['status' => 'connected', 'number' => '34613194131'], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $response = $this->actingAs($user)->getJson(route('chat.whatsapp-status'));

        $response->assertOk();
        $response->assertJson([
            'status' => 'connected',
            'isTeamConnected' => true,
        ]);
        $this->assertSame('34613194131', $team->fresh()->getWhatsAppFrom());
    }

    public function test_dashboard_hides_whatsapp_prompt_when_gateway_connected_without_saved_number(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/status*' => Http::response(['status' => 'connected', 'number' => '34613194131'], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->setSetting('business_config', ['business_name' => 'Acme Corp'], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(__('humano_pricing.dashboard_post_checkout_whatsapp_title'), false);
        $this->assertSame('34613194131', $team->fresh()->getWhatsAppFrom());
    }

    public function test_auto_links_gateway_number_when_team_has_mismatched_whatsapp_from(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/status*' => Http::response(['status' => 'connected', 'number' => '34613194131'], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->setSetting('whatsapp_from', '5499999999999');

        $presentation = TeamWhatsAppChatPresentation::resolveForTeam($team->fresh());

        $this->assertTrue($presentation['teamWhatsAppIsConnected']);
        $this->assertSame('34613194131', $team->fresh()->getWhatsAppFrom());
    }
}
