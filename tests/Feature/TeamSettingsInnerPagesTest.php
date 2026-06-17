<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamSettingsInnerPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_settings_shows_back_button_and_validation_below_inputs(): void
    {
        app()->setLocale('es');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'email']))
            ->assertOk()
            ->assertSee(__('Back to Settings'), false);

        $this->actingAs($user)
            ->from(route('team-settings.edit', ['team' => $team, 'group' => 'email']))
            ->put(route('team-settings.update', $team), [
                'email' => [
                    'mail_from_name' => '',
                    'mail_from_address' => 'not-an-email',
                    'mailer_from_name' => 'Solo nombre',
                    'mailer_from_address' => '',
                ],
            ])
            ->assertRedirect(route('team-settings.edit', ['team' => $team, 'group' => 'email']))
            ->assertSessionHasErrors(['email.mail_from_address', 'email.mailer_from_address']);

        $response = $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'email']));

        $response->assertSee('invalid-feedback d-block', false);
    }

    public function test_stripe_settings_page_is_spanish_with_back_button(): void
    {
        app()->setLocale('es');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'stripe']))
            ->assertOk()
            ->assertSee(__('team_settings.groups.stripe.title'), false)
            ->assertSee(__('team_settings.groups.stripe.subtitle'), false)
            ->assertSee(__('Back to Settings'), false)
            ->assertSee(__('team_settings.fields.stripe_public.label'), false);
    }

    public function test_save_stays_on_same_settings_page_with_spanish_flash(): void
    {
        app()->setLocale('es');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->from(route('team-settings.edit', ['team' => $team, 'group' => 'stripe']))
            ->put(route('team-settings.update', $team), [
                'stripe' => [
                    'stripe_public' => 'pk_test_example',
                ],
            ])
            ->assertRedirect(route('team-settings.edit', ['team' => $team, 'group' => 'stripe']))
            ->assertSessionHas('success', __('team_settings.group_saved', ['group' => __('team_settings.groups.stripe.title')]));
    }
}
