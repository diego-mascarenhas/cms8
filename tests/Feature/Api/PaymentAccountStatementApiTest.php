<?php

namespace Tests\Feature\Api;

use App\Enums\TransactionType;
use App\Models\BankStatement;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentAccountStatementApiTest extends TestCase
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
        Storage::fake('originals');
    }

    public function test_lists_active_payment_accounts_for_team(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;

        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'EUR',
            'name' => 'Cuenta EUR',
            'currency_id' => 978,
            'status' => 1,
        ]);
        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'OFF',
            'name' => 'Inactiva',
            'currency_id' => 978,
            'status' => 0,
        ]);

        $token = $user->createToken('statements-api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/payment-accounts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cuenta EUR')
            ->assertJsonPath('data.0.currency_code', 'EUR');
    }

    public function test_uploads_csv_statement_and_returns_match_summary(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'MP',
            'name' => 'MercadoPago',
            'currency_id' => 32,
            'status' => 1,
        ]);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => TransactionType::INCOME,
            'date' => '2026-07-10',
            'account_id' => $account->id,
            'type_id' => 12,
            'amount' => 1500.50,
            'status' => 2,
            'remarks' => 'Ref: ABC123',
        ]);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => TransactionType::INCOME,
            'date' => '2026-07-20',
            'account_id' => $account->id,
            'type_id' => 12,
            'amount' => 99.00,
            'status' => 2,
        ]);

        $csv = implode("\n", [
            'DATE;amount;REFERENCE;DESCRIPTION',
            '2026-07-10;1500.50;ABC123;Transfer in',
            '2026-07-15;200.00;ONLYCSV;Not in payments',
        ]);
        $file = UploadedFile::fake()->createWithContent('extracto-2026-07.csv', $csv);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment-accounts/'.$account->id.'/statements', [
                'files' => [$file],
                'period_year' => 2026,
                'period' => 'm:7',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('uploaded', 1)
            ->assertJsonPath('statements.0.matched_lines', 1)
            ->assertJsonPath('statements.0.statement_only_lines', 1)
            ->assertJsonPath('statements.0.filename', 'extracto-2026-07.csv')
            ->assertJsonPath('statements.0.period_label', '2026-07');

        $this->assertSame(1, BankStatement::query()->where('payment_account_id', $account->id)->count());
    }

    public function test_uploads_pdf_with_statement_balance_for_quarter(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'BBVA',
            'name' => 'BBVA',
            'currency_id' => 32,
            'status' => 1,
        ]);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => TransactionType::INCOME,
            'date' => '2026-08-15',
            'account_id' => $account->id,
            'type_id' => 12,
            'amount' => 100,
            'status' => 2,
        ]);
        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => TransactionType::EXPENSE,
            'date' => '2026-09-02',
            'account_id' => $account->id,
            'type_id' => 12,
            'amount' => 40,
            'status' => 2,
        ]);

        $file = UploadedFile::fake()->create('extracto-q3.pdf', 20, 'application/pdf');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment-accounts/'.$account->id.'/statements', [
                'files' => [$file],
                'period_year' => 2026,
                'period' => 'q:3',
                'statement_balance' => '60.00',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('balanced', 1)
            ->assertJsonPath('open', 0)
            ->assertJsonPath('statements.0.balanced', true)
            ->assertJsonPath('statements.0.period_label', 'Q3 2026')
            ->assertJsonPath('statements.0.difference', 0);
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
