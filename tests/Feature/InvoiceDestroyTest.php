<?php

namespace Tests\Feature;

use App\Mail\InvoiceDeletedMail;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceDestroyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'invoice.destroy', 'guard_name' => 'web']);

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_admin_can_delete_invoice_and_owner_receives_email(): void
    {
        Mail::fake();

        [$owner, $invoice] = $this->createOwnerWithInvoice();
        $team = $owner->currentTeam;

        $admin = User::factory()->create([
            'email' => 'admin-deleter@example.com',
            'name' => 'Admin Deleter',
        ]);
        $team->users()->attach($admin, ['role' => 'admin']);
        $admin->forceFill(['current_team_id' => $team->id])->save();
        $admin->assignRole('admin');
        $admin->givePermissionTo('invoice.destroy');

        $this->actingAs($admin)
            ->deleteJson(route('invoice.destroy', $invoice->id))
            ->assertOk()
            ->assertJsonPath('message', __('Invoice deleted successfully.'));

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);

        Mail::assertSent(InvoiceDeletedMail::class, function (InvoiceDeletedMail $mail) use ($owner, $invoice): bool
        {
            return $mail->hasTo($owner->email)
                && $mail->invoiceNumber === (string) $invoice->number
                && $mail->deletedByEmail === 'admin-deleter@example.com';
        });
    }

    public function test_owner_deleting_own_invoice_does_not_email_themselves(): void
    {
        Mail::fake();

        [$owner, $invoice] = $this->createOwnerWithInvoice();
        $owner->givePermissionTo('invoice.destroy');

        $this->actingAs($owner)
            ->deleteJson(route('invoice.destroy', $invoice->id))
            ->assertOk();

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        Mail::assertNothingSent();
    }

    public function test_collaborator_cannot_delete_invoice(): void
    {
        Mail::fake();

        [$owner, $invoice] = $this->createOwnerWithInvoice();
        $team = $owner->currentTeam;

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'collaborator']);
        $member->forceFill(['current_team_id' => $team->id])->save();
        $member->assignRole('collaborator');

        $this->actingAs($member)
            ->deleteJson(route('invoice.destroy', $invoice->id))
            ->assertForbidden();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'deleted_at' => null,
        ]);
        Mail::assertNothingSent();
    }

    public function test_admin_sees_delete_button_on_sell_invoice_show(): void
    {
        [$owner, $invoice] = $this->createOwnerWithInvoice();

        $this->actingAs($owner)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee(__('Delete Invoice'), false)
            ->assertSee('deleteInvoice('.$invoice->id.')', false);
    }

    public function test_buy_invoice_show_redirects_to_expense_edit(): void
    {
        [$owner, $invoice] = $this->createOwnerWithInvoice();
        $invoice->forceFill(['operation' => 'buy'])->save();

        $this->actingAs($owner)
            ->get(route('invoice.show', $invoice->id))
            ->assertRedirect(route('expense.edit', $invoice));
    }

    /**
     * @return array{0: User, 1: Invoice}
     */
    private function createOwnerWithInvoice(): array
    {
        $user = User::factory()->withPersonalTeam()->create([
            'email' => 'owner@example.com',
            'name' => 'Team Owner',
        ]);
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'INV-DEL-001',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
            'source_provider' => 'manual',
            'source_reference_id' => 'manual-inv-del-001',
        ]);

        return [$user, $invoice];
    }
}
