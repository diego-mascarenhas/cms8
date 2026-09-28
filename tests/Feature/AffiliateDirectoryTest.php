<?php

namespace Tests\Feature;

use App\Models\BillingAffiliateCommission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AffiliateDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);
        config(['humano_pricing.affiliate_commission_percent' => 30]);
    }

    public function test_platform_admin_sees_affiliates_referrals_and_commissions(): void
    {
        [$user, $affiliate] = $this->platformAdmin([
            'name' => 'Agencia Norte',
            'stripe_id' => 'cus_directory_referrer',
        ]);

        $payingOwner = User::factory()->create([
            'email' => 'cliente.referido@example.com',
        ]);
        $payingTeam = Team::factory()->create([
            'name' => 'Cliente Referido',
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_directory_paying',
            'referred_by' => 'cus_directory_referrer',
        ]);
        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_directory_assistant',
            'stripe_status' => 'active',
            'stripe_price' => 'price_assistant',
            'quantity' => 1,
            'referred_by' => 'cus_directory_referrer',
            'affiliate_commission_percent' => 30,
        ]);

        Team::factory()->create([
            'name' => 'Sin referidos',
            'user_id' => User::factory()->create()->id,
            'stripe_id' => 'cus_no_refs',
            'referred_by' => null,
        ]);

        BillingAffiliateCommission::query()->create([
            'paying_team_id' => $payingTeam->id,
            'referrer_team_id' => $affiliate->id,
            'stripe_invoice_id' => 'in_directory_1',
            'amount_paid_cents' => 10000,
            'currency' => 'eur',
            'commission_percent' => 30,
            'commission_amount_cents' => 3000,
        ]);

        $this->actingAs($user)
            ->get(route('affiliate.index'))
            ->assertOk()
            ->assertSee('Afiliados')
            ->assertSee('Asignar código')
            ->assertSee('text-center')
            ->assertSee('text-end');

        $table = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('affiliate.index'), $this->dataTablePayload());

        $table->assertOk();
        $html = implode(' ', array_map(
            fn (array $row): string => (string) ($row['name'] ?? '').' '.(string) ($row['commission'] ?? '').' '.(string) ($row['action'] ?? ''),
            $table->json('data') ?? [],
        ));
        $this->assertStringContainsString('Agencia Norte', $html);
        $this->assertStringContainsString('30,00', $html);
        $this->assertStringContainsString('ti-eye', $html);
        $this->assertStringNotContainsString('Sin referidos', $html);

        $this->actingAs($user)
            ->get(route('affiliate.show', $affiliate))
            ->assertOk()
            ->assertSee('Cliente Referido')
            ->assertSee('cliente.referido@example.com')
            ->assertSee('cus_directory_paying')
            ->assertSee('sub_directory_assistant')
            ->assertSee('EUR 30,00')
            ->assertSee('in_directory_1')
            ->assertSee('Volver')
            ->assertSee('assignCodeModal')
            ->assertSee('¿Desvincular este código?')
            ->assertDontSee('btn btn-sm btn-icon btn-text-danger');
    }

    public function test_platform_admin_can_assign_and_remove_subscription_code(): void
    {
        [$user, $affiliate] = $this->platformAdmin([
            'stripe_id' => 'cus_assign_referrer',
        ]);

        $payingOwner = User::factory()->create();
        $payingTeam = Team::factory()->create([
            'name' => 'Cliente Nuevo',
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_assign_paying',
            'referred_by' => null,
        ]);
        $subscription = $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'shop',
            'stripe_id' => 'sub_assign_shop',
            'stripe_status' => 'active',
            'stripe_price' => 'price_shop',
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('affiliate.create'))
            ->assertOk()
            ->assertSee('cus_assign_referrer')
            ->assertSee('Código cus_ o sub_');

        $this->actingAs($user)
            ->post(route('affiliate.store'), [
                'team_id' => $affiliate->id,
                'subscription_code' => 'sub_assign_shop',
            ])
            ->assertRedirect(route('affiliate.show', $affiliate));

        $this->assertSame('cus_assign_referrer', $payingTeam->fresh()->referred_by);
        $this->assertSame('cus_assign_referrer', $subscription->fresh()->referred_by);

        $this->actingAs($user)
            ->delete(route('affiliate.referrals.destroy', $affiliate), [
                'subscription_code' => 'sub_assign_shop',
            ])
            ->assertRedirect(route('affiliate.show', $affiliate));

        $this->assertNull($subscription->fresh()->referred_by);
        $this->assertNull($payingTeam->fresh()->referred_by);
    }

    public function test_platform_admin_can_assign_customer_code(): void
    {
        [$user, $affiliate] = $this->platformAdmin([
            'stripe_id' => 'cus_customer_referrer',
        ]);

        $payingOwner = User::factory()->create();
        $payingTeam = Team::factory()->create([
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_customer_paying',
            'referred_by' => null,
        ]);
        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'mailer',
            'stripe_id' => 'sub_customer_mailer',
            'stripe_status' => 'active',
            'stripe_price' => 'price_mailer',
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('affiliate.store'), [
                'team_id' => $affiliate->id,
                'subscription_code' => 'cus_customer_paying',
            ])
            ->assertRedirect(route('affiliate.show', $affiliate));

        $this->assertSame('cus_customer_referrer', $payingTeam->fresh()->referred_by);
        $this->assertSame('cus_customer_referrer', $payingTeam->subscriptions()->first()?->referred_by);
    }

    public function test_invalid_or_unknown_code_is_rejected(): void
    {
        [$user, $affiliate] = $this->platformAdmin([
            'stripe_id' => 'cus_invalid_referrer',
        ]);

        $this->actingAs($user)
            ->from(route('affiliate.create'))
            ->post(route('affiliate.store'), [
                'team_id' => $affiliate->id,
                'subscription_code' => 'not-a-stripe-code',
            ])
            ->assertRedirect(route('affiliate.create'))
            ->assertSessionHasErrors('subscription_code');

        $this->actingAs($user)
            ->from(route('affiliate.show', $affiliate))
            ->post(route('affiliate.store'), [
                'team_id' => $affiliate->id,
                'subscription_code' => 'sub_does_not_exist',
            ])
            ->assertRedirect(route('affiliate.show', $affiliate))
            ->assertSessionHasErrors('subscription_code');
    }

    public function test_admin_outside_platform_team_is_sent_to_billing(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->assignRole('admin');
        $user->forceFill(['current_team_id' => $team->id])->save();

        config(['humano_pricing.platform_team_id' => $team->id + 1000]);

        $this->actingAs($user)
            ->get(route('affiliate.index'))
            ->assertRedirect(route('billing.index'));
    }

    public function test_user_without_billing_access_cannot_open_directory(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        config(['humano_pricing.platform_team_id' => $team->id]);

        $this->actingAs($user)
            ->get(route('affiliate.index'))
            ->assertDeniedForBrowser();
    }

    /**
     * @param  array<string, mixed>  $teamAttrs
     * @return array{0: User, 1: Team}
     */
    private function platformAdmin(array $teamAttrs = []): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->assignRole('admin');
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->forceFill($teamAttrs)->save();

        config(['humano_pricing.platform_team_id' => $team->id]);

        return [$user->fresh(), $team->fresh()];
    }

    /**
     * @return array<string, mixed>
     */
    private function dataTablePayload(): array
    {
        $columns = [];
        foreach (['name', 'owner', 'stripe_id', 'referrals_count', 'commission', 'action'] as $data)
        {
            $columns[] = [
                'data' => $data,
                'name' => $data,
                'searchable' => in_array($data, ['name', 'stripe_id'], true) ? 'true' : 'false',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        return [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'columns' => $columns,
            'order' => [
                ['column' => 0, 'dir' => 'asc'],
            ],
        ];
    }
}
