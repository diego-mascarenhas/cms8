<?php

namespace Tests\Feature;

use App\Jobs\LoadTeamBusinessInsightsJob;
use App\Livewire\Settings\BusinessConfigWizard;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessConfigWizardTeamSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mount_restores_market_insights_from_team_business_config(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $team->setSetting('business_config', [
            'business_name' => 'Acme SL',
            '_insights' => [
                'potential_clients_summary' => '## Informe guardado',
            ],
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        Livewire::actingAs($owner)
            ->test(BusinessConfigWizard::class, ['team' => $team])
            ->assertSet('insights.potential_clients_summary', '## Informe guardado');
    }

    public function test_persist_config_preserves_insights_in_team_setting(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $team->setSetting('business_config', [
            'business_name' => 'Acme SL',
            '_insights' => [
                'potential_clients_summary' => '## Existente',
            ],
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        Livewire::actingAs($owner)
            ->test(BusinessConfigWizard::class, ['team' => $team])
            ->set('config.business_industry', 'Software')
            ->call('nextStep');

        $team->refresh();
        $saved = $team->getSetting('business_config', []);
        $this->assertIsArray($saved);
        $this->assertSame('## Existente', $saved['_insights']['potential_clients_summary'] ?? null);
        $this->assertSame('Software', $saved['business_industry'] ?? null);
    }

    public function test_regenerate_market_insights_dispatches_team_job(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        Livewire::actingAs($owner)
            ->test(BusinessConfigWizard::class, ['team' => $team])
            ->set('step', 6)
            ->set('insights', ['potential_clients_summary' => '## Old'])
            ->set('config.business_industry', 'Retail')
            ->set('config.business_description', 'We sell things')
            ->set('config.business_tagline', 'Best shop')
            ->call('regenerateMarketInsightsReport');

        Queue::assertPushed(LoadTeamBusinessInsightsJob::class, function (LoadTeamBusinessInsightsJob $job) use ($team): bool
        {
            return $job->teamId === $team->id;
        });
    }
}
