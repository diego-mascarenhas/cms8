<?php

namespace Tests\Feature;

use App\Grapesjs\TeamLandingEditable;
use App\Models\TeamSetting;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamLandingPageEditorTest extends TestCase
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

    public function test_guest_cannot_open_team_landing_editor(): void
    {
        $response = $this->get(route('page.team-landing-editor'));

        $response->assertRedirect();
    }

    public function test_authenticated_user_opens_grapesjs_without_creating_team_setting_until_save(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $response = $this->actingAs($user)->get(route('page.team-landing-editor'));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertStringContainsString('window.gjsEditor', $html);
        $this->assertStringContainsString('vendor/laravel-grapesjs/assets/editor.js', $html);
        $this->assertStringContainsString('var returnUrl', $html);
        $this->assertStringContainsString('goReturn', $html);

        $this->assertSame(0, TeamSetting::query()->where('team_id', $teamId)->where('key', TeamLandingEditable::SETTING_KEY)->count());
    }

    public function test_store_persists_landing_gjs_data_in_team_settings(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $team = $user->currentTeam;

        $payload = [
            'laravel-grapesjs-components' => '[]',
            'laravel-grapesjs-styles' => '[]',
            'laravel-grapesjs-css' => '* { margin: 0; }',
            'laravel-grapesjs-html' => '<body><p>Stored</p></body>',
        ];

        $this->actingAs($user)
            ->post(route('page.team-landing-editor.store'), $payload)
            ->assertOk();

        $team->refresh();
        $stored = $team->getSetting(TeamLandingEditable::SETTING_KEY);
        $this->assertIsArray($stored);
        $this->assertSame('<body><p>Stored</p></body>', $stored['html']);
        $this->assertSame('* { margin: 0; }', $stored['css']);
    }

    public function test_second_get_still_has_no_setting_until_save(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        $this->actingAs($user)->get(route('page.team-landing-editor'))->assertOk();
        $this->actingAs($user)->get(route('page.team-landing-editor'))->assertOk();

        $this->assertSame(0, TeamSetting::query()->where('team_id', $teamId)->where('key', TeamLandingEditable::SETTING_KEY)->count());
    }
}
