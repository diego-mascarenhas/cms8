<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientFormReferrerSelectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);
    }

    public function test_client_create_form_renders_referred_by_as_select2_with_enterprise_ids(): void
    {
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('collaborator');

        $withCode = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'type_id' => 1,
            'code' => 'REF-SEL-1',
            'name' => 'Referrer Co',
            'payment_type_id' => null,
            'invoice_type_id' => null,
            'status_id' => 1,
        ]));

        $noCode = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'type_id' => 1,
            'code' => null,
            'name' => 'No Code Yet LLC',
            'payment_type_id' => null,
            'invoice_type_id' => null,
            'status_id' => 1,
        ]));

        $response = $this->actingAs($user)->get(route('client.create'));

        $response->assertOk();
        $response->assertSee('id="referred_by"', false);
        $response->assertSee('name="referred_by"', false);
        $response->assertSee('select2', false);
        $response->assertSee('value="'.(string) $withCode->id.'"', false);
        $response->assertSee('value="'.(string) $noCode->id.'"', false);
    }
}
