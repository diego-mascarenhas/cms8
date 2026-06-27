<?php

namespace Tests\Feature;

use App\Models\PaymentAccount;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentAccountCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            CurrencySeeder::class,
            PaymentTypeSeeder::class,
        ]);
    }

    public function test_admin_can_create_payment_account_with_accepted_payment_types(): void
    {
        $user = $this->makeAdminUser();

        $this->actingAs($user)
            ->post(route('payment-account.store'), [
                'code' => 'CAJA',
                'name' => 'Caja efectivo',
                'currency_id' => 978,
                'status' => 1,
                'payment_type_ids' => [1],
            ])
            ->assertRedirect(route('payment-account.index'))
            ->assertSessionHas('success');

        $account = PaymentAccount::withoutGlobalScopes()->where('code', 'CAJA')->first();

        $this->assertNotNull($account);
        $this->assertSame([1], $account->paymentTypes()->pluck('payment_types.id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_admin_can_update_payment_account_payment_types(): void
    {
        $user = $this->makeAdminUser();
        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'code' => 'PAYPAL',
            'name' => 'PayPal',
            'currency_id' => 840,
            'status' => 1,
        ]);
        $account->paymentTypes()->sync([7]);

        $this->actingAs($user)
            ->put(route('payment-account.update', $account), [
                'code' => 'PAYPAL',
                'name' => 'PayPal EUR',
                'currency_id' => 978,
                'status' => 1,
                'payment_type_ids' => [7, 2],
            ])
            ->assertRedirect(route('payment-account.index'));

        $this->assertSame([2, 7], $account->fresh()->paymentTypes()->pluck('payment_types.id')->map(fn ($id) => (int) $id)->sort()->values()->all());
    }

    public function test_admin_can_edit_inactive_payment_account(): void
    {
        $user = $this->makeAdminUser();
        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'code' => 'OLD',
            'name' => 'Cuenta inactiva',
            'currency_id' => 978,
            'status' => 0,
        ]);
        $account->paymentTypes()->sync([2]);

        $this->actingAs($user)
            ->get(route('payment-account.edit', $account))
            ->assertOk()
            ->assertSee('Cuenta inactiva', false);

        $this->actingAs($user)
            ->put(route('payment-account.update', $account), [
                'code' => 'OLD',
                'name' => 'Cuenta reactivada',
                'currency_id' => 978,
                'status' => 1,
                'payment_type_ids' => [2],
            ])
            ->assertRedirect(route('payment-account.index'));

        $this->assertSame(1, (int) $account->fresh()->status);
        $this->assertSame('Cuenta reactivada', $account->fresh()->name);
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }
}
