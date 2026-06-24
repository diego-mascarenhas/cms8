<?php

namespace Tests\Feature;

use App\Console\Commands\HumanoDemoOnboardingCaptureStateCommand;
use App\Models\Module;
use App\Models\User;
use App\Services\TeamWhatsAppChatPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoOnboardingCaptureStateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_shows_onboarding_banner_on_dashboard(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->forceFill(['name' => 'Demo'])->save();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->artisan('humano:demo-onboarding-capture-state', ['state' => 'reset'])
            ->assertSuccessful();

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(__('Configure business'), false);
    }

    public function test_complete_hides_onboarding_banner_on_dashboard(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->forceFill(['name' => 'Demo'])->save();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->artisan('humano:demo-onboarding-capture-state', ['state' => 'complete'])
            ->assertSuccessful();

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(__('Configure business'), false);
        $response->assertDontSee(__('humano_pricing.dashboard_post_checkout_whatsapp_button'), false);
    }

    public function test_complete_marks_business_configured_and_whatsapp_connected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->forceFill(['name' => 'Demo'])->save();

        $this->artisan('humano:demo-onboarding-capture-state', ['state' => 'complete'])
            ->assertSuccessful();

        $team->refresh();

        $this->assertTrue($team->hasCompletedBusinessConfiguration());
        $this->assertSame('34999000999', $team->getWhatsAppFrom());

        $presentation = TeamWhatsAppChatPresentation::resolveForTeam($team);

        $this->assertTrue($presentation['teamWhatsAppIsConnected']);
    }

    public function test_complete_enables_list60_module_for_tutorial_capture(): void
    {
        Module::query()->firstOrCreate(
            ['key' => 'list60'],
            [
                'name' => 'List 60',
                'icon' => 'list',
                'description' => 'List 60',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->forceFill(['name' => 'Demo'])->save();

        $this->artisan('humano:demo-onboarding-capture-state', ['state' => 'complete'])
            ->assertSuccessful();

        $team->refresh();

        $this->assertTrue($team->hasModule('list60'));
    }

    public function test_reset_clears_business_configuration(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->forceFill(['name' => 'Demo'])->save();
        $team->setSetting('business_config', ['business_name' => 'Acme'], [
            'type' => 'json',
            'group' => 'business-config',
        ]);
        $team->setSetting(HumanoDemoOnboardingCaptureStateCommand::SETTING_SIMULATE_WHATSAPP_CONNECTED, true, [
            'type' => 'boolean',
            'group' => 'onboarding-capture',
        ]);

        $this->artisan('humano:demo-onboarding-capture-state', ['state' => 'reset'])
            ->assertSuccessful();

        $team->refresh();

        $this->assertFalse($team->hasCompletedBusinessConfiguration());
        $this->assertFalse(
            filter_var(
                $team->getSetting(HumanoDemoOnboardingCaptureStateCommand::SETTING_SIMULATE_WHATSAPP_CONNECTED),
                FILTER_VALIDATE_BOOL,
            ),
        );
    }
}
