<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\Finance\PaymentStatusUpdateService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    private function createPaymentForTeam(User $owner): Payment
    {
        $team = $owner->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank',
            'name' => 'Bank',
            'symbol' => '€',
            'currency_id' => 978,
            'status' => 1,
        ]);

        $type = PaymentType::query()->create(['name' => 'Transfer']);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-001',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
        ]);

        return Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'invoice_id' => $invoice->id,
            'transaction_type' => 'income',
            'date' => now()->toDateString(),
            'account_id' => $account->id,
            'type_id' => $type->id,
            'amount' => 100,
            'status' => 1,
        ]);
    }

    public function test_team_owner_can_update_payment_status(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $owner->assignRole('admin');
        $payment = $this->createPaymentForTeam($owner);

        $this->actingAs($owner)
            ->from(route('invoice.show', $payment->invoice_id))
            ->patch(route('payments.update-status', $payment), [
                'status' => 2,
            ])
            ->assertRedirect(route('invoice.show', $payment->invoice_id))
            ->assertSessionHas('success');

        $this->assertSame(2, $payment->fresh()->status);
    }

    public function test_non_owner_cannot_update_payment_status(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $owner->assignRole('admin');
        $payment = $this->createPaymentForTeam($owner);

        $member = User::factory()->create();
        $team = $owner->ownedTeams()->first();
        $team->users()->attach($member, ['role' => 'editor']);
        $member->current_team_id = $team->id;
        $member->save();
        $member->assignRole('admin');

        $this->actingAs($member)
            ->patch(route('payments.update-status', $payment), [
                'status' => 2,
            ])
            ->assertDeniedForBrowser();

        $this->assertSame(1, $payment->fresh()->status);
    }

    public function test_service_denies_update_for_non_owner(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $payment = $this->createPaymentForTeam($owner);

        $member = User::factory()->create();
        $team = $owner->ownedTeams()->first();
        $team->users()->attach($member, ['role' => 'editor']);
        $member->current_team_id = $team->id;
        $member->save();

        $service = app(PaymentStatusUpdateService::class);

        $this->assertFalse($service->canUpdateStatus($member, $payment));
        $this->assertTrue($service->canUpdateStatus($owner, $payment));
    }
}
