<?php

namespace Tests\Feature;

use App\Models\ContactLanguageVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactLanguageVariantCombinationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_combinations_with_few_collaborators_runs_on_sqlite(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $combinations = ContactLanguageVariant::getCombinationsWithFewCollaborators(10, 15, $user->currentTeam->id);

        $this->assertCount(0, $combinations);
    }

    public function test_collaborator_dashboard_does_not_500_for_authenticated_user(): void
    {
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('collaborator');
        $this->actingAs($user->fresh());

        $response = $this->get(route('dashboard.collaborator'));

        $response->assertOk();
    }
}
