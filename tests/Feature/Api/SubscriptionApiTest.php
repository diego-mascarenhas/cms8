<?php

namespace Tests\Feature\Api;

use App\Models\ServiceSync;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_list_and_detail_include_metadata(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $sync = ServiceSync::query()->create([
            'stripe_id' => 'sub_metadata_test',
            'team_id' => $team->id,
            'status' => 'active',
            'plan_name' => 'Hosting anual',
            'customer_name' => 'Lizama Abogados',
            'price_currency' => 'eur',
            'amount_total' => 99,
            'data' => [
                'metadata' => [
                    'category' => 'hosting',
                    'domain' => 'lizama.test',
                ],
            ],
        ]);

        $token = $user->createToken('subscriptions')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/subscriptions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.id', $sync->id)
            ->assertJsonPath('data.data.0.customer_name', 'Lizama Abogados');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/subscriptions/'.$sync->id)
            ->assertOk()
            ->assertJsonPath('data.metadata.domain', 'lizama.test')
            ->assertJsonPath('data.metadata.category', 'hosting')
            ->assertJsonPath('data.name', 'Hosting anual');
    }
}
