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

    public function test_admin_can_list_payment_accounts_with_datatable(): void
    {
        $user = $this->makeAdminUser();
        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'code' => 'CAJA',
            'name' => 'Caja principal',
            'currency_id' => 978,
            'status' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('payment-account.index'))
            ->assertOk()
            ->assertSee('payment-account-table', false)
            ->assertSee('Cuentas de pago', false);

        $response = $this->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('payment-account.index', $this->dataTablesQueryParams('Caja principal')));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertStringContainsString('Caja principal', (string) data_get($response->json('data.0'), 'name'));
        $this->assertSame($account->id, (int) data_get($response->json('data.0'), 'id'));
    }

    public function test_datatable_includes_inactive_payment_accounts(): void
    {
        $user = $this->makeAdminUser();
        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'code' => 'OLD',
            'name' => 'Cuenta archivada',
            'currency_id' => 978,
            'status' => 0,
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('payment-account.index', $this->dataTablesQueryParams('archivada')));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertStringContainsString('Cuenta archivada', (string) data_get($response->json('data.0'), 'name'));
        $this->assertStringContainsString('Inactiva', (string) data_get($response->json('data.0'), 'status'));
    }

    public function test_datatable_lists_active_accounts_before_inactive(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;

        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'ZZZ',
            'name' => 'Zeta inactiva',
            'currency_id' => 978,
            'status' => 0,
        ]);
        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'AAA',
            'name' => 'Alfa activa',
            'currency_id' => 978,
            'status' => 1,
        ]);

        $params = $this->dataTablesQueryParams();
        $params['order'] = [
            ['column' => 5, 'dir' => 'desc'],
            ['column' => 1, 'dir' => 'asc'],
        ];

        $response = $this->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('payment-account.index', $params));

        $response->assertOk();
        $this->assertSame(2, (int) $response->json('recordsFiltered'));
        $this->assertStringContainsString('Alfa activa', (string) data_get($response->json('data.0'), 'name'));
        $this->assertStringContainsString('Zeta inactiva', (string) data_get($response->json('data.1'), 'name'));
    }

    public function test_admin_can_view_payment_account_movements(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'CAJA',
            'name' => 'Caja Fuerte',
            'currency_id' => 32,
            'status' => 1,
        ]);

        $otherAccount = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'BANK',
            'name' => 'Banco',
            'currency_id' => 32,
            'status' => 1,
        ]);

        \App\Models\Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => \App\Enums\TransactionType::INCOME,
            'date' => now()->toDateString(),
            'account_id' => $account->id,
            'type_id' => 1,
            'amount' => 99,
            'status' => 2,
        ]);

        \App\Models\Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => \App\Enums\TransactionType::INCOME,
            'date' => now()->toDateString(),
            'account_id' => $otherAccount->id,
            'type_id' => 1,
            'amount' => 50,
            'status' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('payment-account.show', $account))
            ->assertOk()
            ->assertSee('Caja Fuerte', false)
            ->assertSee('payment-account-movements-table', false)
            ->assertSee('99.00', false);

        $response = $this->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('payment-account.show', array_merge(
                ['paymentAccount' => $account->id],
                $this->paymentMovementsDataTablesParams(),
            )));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertStringContainsString('99', (string) data_get($response->json('data.0'), 'amount'));
    }

    public function test_cannot_view_payment_account_from_another_team(): void
    {
        $user = $this->makeAdminUser();
        $otherTeam = Team::factory()->create();

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $otherTeam->id,
            'code' => 'OTHER',
            'name' => 'Otra caja',
            'currency_id' => 32,
            'status' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('payment-account.show', $account->id))
            ->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentMovementsDataTablesParams(): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'desc']],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'transaction_indicator', 'name' => 'transaction_indicator', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'date', 'name' => 'date', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'invoice_id', 'name' => 'invoice_id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'enterprise_id', 'name' => 'enterprise_id', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'type_id', 'name' => 'type_id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'amount', 'name' => 'amount', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'status', 'name' => 'status', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dataTablesQueryParams(string $search = ''): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => $search, 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'code', 'name' => 'code', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'currency_code', 'name' => 'currency_code', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'payment_types', 'name' => 'payment_types', 'searchable' => 'true', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'status', 'name' => 'status', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
            ],
        ];
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
