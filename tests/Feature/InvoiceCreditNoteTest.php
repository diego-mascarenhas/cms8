<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\StripeInvoiceCreditNoteService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceCreditNoteTest extends TestCase
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

    public function test_team_owner_can_issue_stripe_credit_note_from_invoice_show(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $team->setSetting('stripe_secret', 'sk_test_example', [
            'type' => 'string',
            'group' => 'stripe',
            'is_encrypted' => false,
        ]);

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
            'number' => '0005-0100',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_test_credit_note',
        ]);

        $this->mock(StripeInvoiceCreditNoteService::class, function ($mock): void
        {
            $mock->shouldReceive('issueForInvoice')
                ->once()
                ->withArgs(function ($invoice, string $reason): bool
                {
                    return $reason === 'order_change';
                })
                ->andReturn([
                    'credit_note_id' => 'cn_test_001',
                    'number' => '0005-CN-01',
                    'amount' => 100.0,
                ]);
        });

        $this->actingAs($user)
            ->post(route('invoice.credit-notes.store', $invoice), [
                'reason' => 'order_change',
            ])
            ->assertRedirect(route('invoice.show', $invoice->id))
            ->assertSessionHas('success');
    }

    public function test_non_owner_cannot_issue_credit_note(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $owner->assignRole('admin');
        $team = $owner->ownedTeams()->first();
        $team->setSetting('stripe_secret', 'sk_test_example', [
            'type' => 'string',
            'group' => 'stripe',
            'is_encrypted' => false,
        ]);

        $member = User::factory()->create();
        $member->assignRole('admin');
        $team->users()->attach($member, ['role' => 'admin']);
        $member->forceFill(['current_team_id' => $team->id])->save();

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
            'number' => '0005-0101',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_test_credit_note_2',
        ]);

        $this->actingAs($member)
            ->post(route('invoice.credit-notes.store', $invoice), [
                'reason' => 'order_change',
            ])
            ->assertForbidden();
    }

    public function test_invoice_show_displays_credit_note_form_only_for_team_owner_on_stripe_invoice(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $owner->assignRole('admin');
        $team = $owner->ownedTeams()->first();
        $team->setSetting('stripe_secret', 'sk_test_example', [
            'type' => 'string',
            'group' => 'stripe',
            'is_encrypted' => false,
        ]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

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
            'number' => '0005-0102',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_test_credit_note_3',
        ]);

        $member = User::factory()->create();
        $member->assignRole('admin');
        $team->users()->attach($member, ['role' => 'admin']);
        $member->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($owner)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee(__('invoice_credit_note.create_title'), false)
            ->assertSee(__('invoice_credit_note.issue_button'), false)
            ->assertSee('creditNoteModal', false);

        $this->actingAs($member)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertDontSee(__('invoice_credit_note.create_title'), false)
            ->assertDontSee('creditNoteModal', false);
    }

    public function test_invoice_show_displays_disabled_credit_note_form_when_stripe_secret_missing(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $owner->assignRole('admin');
        $team = $owner->ownedTeams()->first();
        $owner->forceFill(['current_team_id' => $team->id])->save();

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
            'number' => '0005-0103',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_test_credit_note_4',
        ]);

        $this->actingAs($owner)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee(__('invoice_credit_note.issue_button'), false)
            ->assertSee(__('invoice_credit_note.errors.stripe_not_configured'), false);
    }

    public function test_credit_note_show_links_to_original_invoice(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $owner->assignRole('admin');
        $team = $owner->ownedTeams()->first();
        $owner->forceFill(['current_team_id' => $team->id])->save();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $original = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0500',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_original_for_cn',
        ]);

        $creditNote = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 2,
            'operation' => 'sell',
            'number' => 'CN-0005-0001',
            'date' => now()->toDateString(),
            'due_date' => null,
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 4,
            'source_provider' => 'stripe',
            'source_reference_id' => 'cn_linked_to_original',
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'cn_linked_to_original',
            'number' => '0005-0500-CN-01',
            'status' => 'issued',
            'currency' => 'eur',
            'paid' => true,
            'raw_payload' => [
                'id' => 'cn_linked_to_original',
                'number' => '0005-0500-CN-01',
                'invoice' => 'in_original_for_cn',
                'pdf' => 'https://pay.stripe.com/credit_notes/example.pdf',
            ],
        ]);

        $this->actingAs($owner)
            ->get(route('invoice.show', $creditNote->id))
            ->assertOk()
            ->assertSee(__('invoice_credit_note.view_original'), false)
            ->assertSee(route('invoice.show', $original->id), false)
            ->assertSee('#'.$original->number, false)
            ->assertDontSee(__('Payments'), false)
            ->assertDontSee(__('No payments linked to this invoice'), false)
            ->assertSee(__('Print'), false)
            ->assertSee(__('Download'), false)
            ->assertSee('https://pay.stripe.com/credit_notes/example.pdf', false);
    }

    public function test_invoice_show_links_to_existing_credit_note_instead_of_create_modal(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $owner->assignRole('admin');
        $team = $owner->ownedTeams()->first();
        $team->setSetting('stripe_secret', 'sk_test_example', [
            'type' => 'string',
            'group' => 'stripe',
            'is_encrypted' => false,
        ]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

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
            'number' => '0005-0600',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_has_existing_cn',
        ]);

        $creditNote = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 2,
            'operation' => 'sell',
            'number' => 'CN-0005-0099',
            'date' => now()->toDateString(),
            'due_date' => null,
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 4,
            'source_provider' => 'stripe',
            'source_reference_id' => 'cn_for_existing_invoice',
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'cn_for_existing_invoice',
            'number' => '0005-0600-CN-01',
            'status' => 'issued',
            'currency' => 'eur',
            'paid' => true,
            'raw_payload' => [
                'id' => 'cn_for_existing_invoice',
                'number' => '0005-0600-CN-01',
                'invoice' => 'in_has_existing_cn',
            ],
        ]);

        $this->actingAs($owner)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee(__('invoice_credit_note.view_existing'), false)
            ->assertSee(route('invoice.show', $creditNote->id), false)
            ->assertSee('#'.$creditNote->number, false)
            ->assertDontSee(__('invoice_credit_note.create_title'), false)
            ->assertDontSee('data-bs-target="#creditNoteModal"', false);
    }
}
