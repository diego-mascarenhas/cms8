<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseDocumentNumberLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->user = User::factory()->withPersonalTeam()->create();
        $team = $this->user->ownedTeams()->first();
        $this->user->forceFill(['current_team_id' => $team->id])->save();
        $this->user->assignRole('admin');
    }

    public function test_document_number_in_expense_table_links_to_invoice_link_screen(): void
    {
        $teamId = (int) $this->user->current_team_id;

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'bank-test',
            'name' => 'Bank test',
            'symbol' => 'EUR',
            'currency_id' => 978,
            'status' => 1,
        ]);

        $paymentType = PaymentType::query()->create([
            'name' => 'Transfer',
        ]);

        $payment = Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => null,
            'transaction_type' => 'expense',
            'date' => '2026-06-22',
            'invoice_id' => null,
            'account_id' => $account->id,
            'type_id' => $paymentType->id,
            'amount' => 100,
            'remarks' => 'Número de documento: 12345678A | Concepto: Test',
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->expenseDataTableUrl('12345678A'));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));

        $invoiceColumnHtml = (string) $response->json('data.0.invoice_id');
        $this->assertStringContainsString(route('payments.link-invoice', $payment->id), $invoiceColumnHtml);
        $this->assertStringContainsString('12345678A', $invoiceColumnHtml);
    }

    private function expenseDataTableUrl(string $searchValue): string
    {
        $query = $this->expenseDataTableBaseQuery();
        $query['search'] = ['value' => $searchValue, 'regex' => 'false'];

        return route('expense.index').'?'.http_build_query($query);
    }

    /**
     * @return array<string, mixed>
     */
    private function expenseDataTableBaseQuery(): array
    {
        $columns = [];
        foreach ($this->expenseDataTableColumnDefinitions() as $definition)
        {
            $columns[] = array_merge($definition, [
                'search' => ['value' => '', 'regex' => 'false'],
            ]);
        }

        return [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'desc']],
            'columns' => $columns,
        ];
    }

    /**
     * @return array<int, array{data: string, name: string, searchable: string, orderable: string}>
     */
    private function expenseDataTableColumnDefinitions(): array
    {
        return [
            ['data' => 'id', 'name' => 'id', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'date', 'name' => 'date', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'enterprise_id', 'name' => 'enterprise_id', 'searchable' => 'true', 'orderable' => 'false'],
            ['data' => 'invoice_id', 'name' => 'invoice_id', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'account_id', 'name' => 'account_id', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'type_id', 'name' => 'type_id', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'amount', 'name' => 'amount', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'status', 'name' => 'status', 'searchable' => 'false', 'orderable' => 'true'],
            ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false'],
        ];
    }
}
