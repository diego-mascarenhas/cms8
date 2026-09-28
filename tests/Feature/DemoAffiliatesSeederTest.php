<?php

namespace Tests\Feature;

use App\Models\AffiliateInvitation;
use App\Models\BillingAffiliateCommission;
use App\Models\User;
use Database\Seeders\DemoAffiliatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoAffiliatesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_affiliates_seeder_creates_demo_data(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'email' => 'admin@humano.app',
            'name' => 'Admin Demo',
        ]);
        $admin->assignRole('admin');

        $team = $admin->ownedTeams()->create([
            'name' => 'Demo',
            'personal_team' => false,
        ]);
        $admin->teams()->attach($team->id, ['role' => 'admin']);
        $admin->forceFill(['current_team_id' => $team->id])->save();

        config(['humano_pricing.affiliate_commission_percent' => 30]);

        $this->seed(DemoAffiliatesSeeder::class);

        $team->refresh();

        $this->assertTrue($team->canUseAffiliateProgram());
        $this->assertSame(DemoAffiliatesSeeder::DEMO_REFERRER_STRIPE_ID, $team->stripe_id);
        $this->assertGreaterThanOrEqual(3, AffiliateInvitation::query()->where('team_id', $team->id)->count());
        $this->assertGreaterThanOrEqual(4, BillingAffiliateCommission::query()->where('referrer_team_id', $team->id)->count());
        $this->assertDatabaseHas('subscriptions', [
            'stripe_id' => 'sub_demo_norte_assistant',
            'type' => 'assistant',
            'referred_by' => DemoAffiliatesSeeder::DEMO_REFERRER_STRIPE_ID,
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'stripe_id' => 'sub_demo_norte_hosting',
            'type' => 'hosting',
        ]);
        $this->assertDatabaseHas('teams', [
            'name' => 'Demo · Afiliado Leticia',
            'stripe_id' => 'cus_demo_referrer_leticia',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'stripe_id' => 'sub_demo_clinica_cliente_assistant',
            'type' => 'assistant',
            'referred_by' => 'cus_demo_ref_paying_clinica',
        ]);
        $this->assertDatabaseMissing('subscriptions', [
            'stripe_id' => 'sub_demo_clinica_assistant',
        ]);

        $opened = AffiliateInvitation::query()
            ->where('team_id', $team->id)
            ->whereNotNull('opened_at')
            ->count();

        $this->assertGreaterThanOrEqual(2, $opened);
    }
}
