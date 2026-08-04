<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\BankStatement;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Team;
use App\Models\User;
use App\Services\Finance\PaymentAccountStatementUploadService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentAccountStatementUploadTest extends TestCase
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

    public function test_admin_can_upload_csv_statement_and_validate_against_payments(): void
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
            'DATE;AMOUNT;REFERENCE;DESCRIPTION',
            '2026-07-10;1500.50;ABC123;Transfer in',
            '2026-07-15;200.00;ONLYCSV;Not in payments',
        ]);

        $file = UploadedFile::fake()->createWithContent('extracto-2026-07.csv', $csv);

        $this->actingAs($user)
            ->post(route('payment-account.statements.store', $account), [
                'files' => [$file],
            ])
            ->assertRedirect(route('payment-account.show', $account))
            ->assertSessionHas('success');

        $statement = BankStatement::query()
            ->where('payment_account_id', $account->id)
            ->first();

        $this->assertNotNull($statement);
        $this->assertSame(2026, $statement->period_year);
        $this->assertSame(7, $statement->period_month);
        $this->assertTrue($statement->isDownloadable());
        $this->assertSame(1, (int) data_get($statement->validation_summary, 'matched'));
        $this->assertSame(1, (int) data_get($statement->validation_summary, 'statement_only'));
        $this->assertSame(1, (int) data_get($statement->validation_summary, 'payment_only'));
        $this->assertSame(2, $statement->lines()->count());

        Storage::disk('originals')->assertExists($statement->storage_path);

        $this->actingAs($user)
            ->get(route('payment-account.show', $account))
            ->assertOk()
            ->assertSee('2026-07', false)
            ->assertSee('Validación de extractos', false);

        $this->actingAs($user)
            ->get(route('payment-account.statements.download', [$account, $statement]))
            ->assertOk();
    }

    public function test_period_can_be_forced_and_multiple_files_accepted(): void
    {
        $user = $this->makeAdminUser();
        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'code' => 'CAJA',
            'name' => 'Caja Fuerte',
            'currency_id' => 32,
            'status' => 1,
        ]);

        $pdf = UploadedFile::fake()->create('resumen-abril.pdf', 20, 'application/pdf');
        $csv = UploadedFile::fake()->createWithContent(
            'movs.csv',
            "FECHA;IMPORTE\n2026-01-05;10.00\n",
        );

        $this->actingAs($user)
            ->post(route('payment-account.statements.store', $account), [
                'files' => [$pdf, $csv],
                'period_year' => 2026,
                'period_month' => 4,
            ])
            ->assertRedirect(route('payment-account.show', $account));

        $this->assertSame(2, BankStatement::query()->where('payment_account_id', $account->id)->count());
        $this->assertTrue(
            BankStatement::query()
                ->where('payment_account_id', $account->id)
                ->where('period_month', 4)
                ->where('period_year', 2026)
                ->exists(),
        );
    }

    public function test_infer_period_from_filename(): void
    {
        $service = app(PaymentAccountStatementUploadService::class);

        $this->assertSame([2026, 7], $service->inferPeriodFromFilename('extracto-2026-07.csv'));
        $this->assertSame([2026, 4], $service->inferPeriodFromFilename('extracto-abril-2026.pdf'));
        $this->assertSame([2026, 11], $service->inferPeriodFromFilename('movimientos_11_2026.csv'));
    }

    public function test_cannot_download_statement_from_another_account(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'A',
            'name' => 'A',
            'currency_id' => 32,
            'status' => 1,
        ]);
        $other = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'B',
            'name' => 'B',
            'currency_id' => 32,
            'status' => 1,
        ]);

        $statement = BankStatement::query()->create([
            'team_id' => $teamId,
            'payment_account_id' => $other->id,
            'provider' => BankStatement::PROVIDER_UPLOAD,
            'period_year' => 2026,
            'period_month' => 7,
            'source' => BankStatement::SOURCE_UPLOAD,
            'original_filename' => 'x.csv',
            'storage_path' => 'bank-statements/x.csv',
            'disk' => 'originals',
        ]);

        Storage::disk('originals')->put('bank-statements/x.csv', 'DATE;AMOUNT\n2026-07-01;1\n');

        $this->actingAs($user)
            ->get(route('payment-account.statements.download', [$account, $statement]))
            ->assertNotFound();
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
