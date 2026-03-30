<?php

namespace Tests\Feature;

use App\Livewire\Settings\BusinessConfigWizard;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessConfigWizardCountrySelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_select_lists_seeded_iso_countries_by_name(): void
    {
        $this->seed(CountrySeeder::class);
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        Livewire::actingAs($owner)
            ->test(BusinessConfigWizard::class, ['team' => $team])
            ->call('goToStep', 2)
            ->assertSee('value="Argentina"', false)
            ->assertSee('value="Zimbabue"', false);
    }
}
