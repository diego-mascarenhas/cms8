<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\HumanoPublicPaymentLinkCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BusinessConfigurationPromptDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_whatsapp_qr_link_beside_configure_business_when_profile_incomplete(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(
            __('Welcome name to app onboarding banner', ['app' => config('app.name')]),
            false,
        );
        $response->assertSee(route('registration.onboarding.qr'), false);
        $response->assertSee(__('Configure business'), false);
    }

    public function test_dashboard_hides_business_prompt_when_profile_complete(): void
    {
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
        $response->assertDontSee(
            __('Welcome name to app onboarding banner', ['app' => config('app.name')]),
            false,
        );
    }

    public function test_dashboard_shows_whatsapp_only_prompt_when_post_checkout_session_and_business_complete(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->setSetting('business_config', ['business_name' => 'Acme Corp'], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $this->actingAs($user);

        $response = $this->withSession([
            HumanoPublicPaymentLinkCheckout::SESSION_SHOW_DASHBOARD_WHATSAPP_QR_CTA => true,
        ])->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(__('humano_pricing.dashboard_post_checkout_whatsapp_title'), false);
        $response->assertSee(route('registration.onboarding.qr'), false);
        $response->assertDontSee(__('Configure business'), false);
    }

    public function test_dashboard_shows_whatsapp_qr_when_local_driver_disconnected_and_business_complete(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/status*' => Http::response(['status' => 'disconnected'], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->setSetting('business_config', ['business_name' => 'Acme Corp'], [
            'type' => 'json',
            'group' => 'business-config',
        ]);
        $team->setSetting('whatsapp_from', '34613194131');

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(__('humano_pricing.dashboard_post_checkout_whatsapp_title'), false);
        $response->assertSee(route('registration.onboarding.qr'), false);
        $response->assertDontSee(__('Configure business'), false);
    }

    public function test_dashboard_hides_whatsapp_qr_when_local_driver_connected_and_business_complete(): void
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
        $team->setSetting('whatsapp_from', '34613194131');

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(__('humano_pricing.dashboard_post_checkout_whatsapp_title'), false);
        $response->assertDontSee(route('registration.onboarding.qr'), false);
    }

    public function test_dashboard_hides_whatsapp_qr_when_twilio_driver_even_if_disconnected(): void
    {
        Config::set('whatsapp.driver', 'twilio');

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
    }
}
