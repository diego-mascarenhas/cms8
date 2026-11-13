<?php

namespace Tests\Feature\Api;

use App\Models\SubscriptionProduct;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_history_returns_active_plans_and_hides_them_without_access(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id, 'stripe_id' => null]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $team->subscriptions()->create([
            'user_id' => $user->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_history_assistant',
            'stripe_status' => 'active',
            'stripe_price' => 'price_assistant',
            'quantity' => 1,
        ]);
        $team->subscriptions()->create([
            'user_id' => $user->id,
            'type' => 'mailer',
            'stripe_id' => 'sub_history_mailer',
            'stripe_status' => 'canceled',
            'stripe_price' => 'price_mailer',
            'quantity' => 1,
        ]);

        SubscriptionProduct::query()->create([
            'name' => 'Mailer Basic',
            'category' => 'mailer',
            'plan' => 'basic',
            'type' => 'mailer',
            'currency' => 'eur',
            'unit_amount' => 15.99,
            'recurring_interval' => 'month',
            'active' => true,
        ]);
        SubscriptionProduct::query()->create([
            'name' => 'Ads',
            'category' => 'ads',
            'type' => 'ads',
            'currency' => 'eur',
            'unit_amount' => 49,
            'recurring_interval' => 'month',
            'active' => false,
        ]);

        $token = $user->createToken('billing-history')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/billing/history');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.plans')
            ->assertJsonPath('data.plans.0.type', 'assistant')
            ->assertJsonPath('data.plans.0.status', 'active')
            ->assertJsonCount(2, 'data.products')
            ->assertJsonPath('data.products.0.name', 'Ads')
            ->assertJsonPath('data.products.0.active', false)
            ->assertJsonPath('data.products.0.category_label', 'Ads')
            ->assertJsonPath('data.products.1.name', 'Mailer Basic')
            ->assertJsonPath('data.products.1.active', true)
            ->assertJsonPath('data.products.1.interval', 'Mensual')
            ->assertJsonPath('data.invoices', [])
            ->assertJsonMissingPath('data.app_plans');

        $blocked = User::factory()->create();
        $blocked->teams()->attach($team->id, ['role' => 'collaborator']);
        $blocked->forceFill(['current_team_id' => $team->id])->save();

        $blockedToken = $blocked->createToken('billing-history-blocked')->plainTextToken;

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$blockedToken)
            ->getJson('/api/billing/history')
            ->assertForbidden();
    }
}
