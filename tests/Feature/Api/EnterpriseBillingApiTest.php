<?php

namespace Tests\Feature\Api;

use App\Models\Enterprise;
use App\Models\Team;
use App\Models\User;
use App\Services\Stripe\EnterpriseCardSetup;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnterpriseBillingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_without_stripe_has_no_card(): void
    {
        [$user, $team] = $this->adminTeam();
        $enterprise = $this->enterprise($team);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('billing')->plainTextToken)
            ->getJson('/api/enterprises/'.$enterprise->id.'/billing')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('registered', false)
            ->assertJsonPath('card', null)
            ->assertJsonPath('cards', []);
    }

    public function test_choose_rejects_an_invalid_payment_method(): void
    {
        [$user, $team] = $this->adminTeam();
        $enterprise = $this->enterprise($team);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('billing')->plainTextToken)
            ->postJson('/api/enterprises/'.$enterprise->id.'/billing/default', [
                'payment_method' => 'card_123',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'La tarjeta no es válida.');
    }

    public function test_setup_returns_the_stripe_client_secret(): void
    {
        [$user, $team] = $this->adminTeam();
        $enterprise = $this->enterprise($team);
        $this->mock(EnterpriseCardSetup::class, function ($mock) use ($enterprise)
        {
            $mock->shouldReceive('begin')
                ->once()
                ->withArgs(fn (Enterprise $found): bool => $found->id === $enterprise->id)
                ->andReturn([
                    'client_secret' => 'seti_test_secret',
                    'publishable_key' => 'pk_test_example',
                ]);
        });

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('billing')->plainTextToken)
            ->postJson('/api/enterprises/'.$enterprise->id.'/billing/setup')
            ->assertOk()
            ->assertJsonPath('client_secret', 'seti_test_secret')
            ->assertJsonPath('publishable_key', 'pk_test_example');
    }

    public function test_confirm_rejects_an_invalid_setup_intent(): void
    {
        [$user, $team] = $this->adminTeam();
        $enterprise = $this->enterprise($team);

        $this->withHeader('Authorization', 'Bearer '.$user->createToken('billing')->plainTextToken)
            ->postJson('/api/enterprises/'.$enterprise->id.'/billing/confirm', [
                'setup_intent' => 'pi_not_a_setup',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'La confirmación de la tarjeta no es válida.');
    }

    private function enterprise(Team $team): Enterprise
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        return Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'type_id' => 1,
            'status_id' => 1,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function adminTeam(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return [$user, $team];
    }
}
