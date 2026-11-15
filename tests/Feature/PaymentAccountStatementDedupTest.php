<?php

namespace Tests\Feature;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
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

class PaymentAccountStatementDedupTest extends TestCase
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

    public function test_reuploading_same_csv_does_not_duplicate_lines_for_account(): void
    {
        $user = $this->makeAdminUser();
        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'code' => 'RURAL',
            'name' => 'Caja Rural',
            'currency_id' => 978,
            'status' => 1,
        ]);

        $csv = implode("\n", [
            'DATE;AMOUNT;EXTERNAL_ID;REFERENCE;DESCRIPTION',
            '2026-09-10;24.19;EXT-100;network;First',
            '2026-09-12;2.99;EXT-200;interserver;Second',
        ]);
        $file = UploadedFile::fake()->createWithContent('extracto.csv', $csv);

        $this->actingAs($user)
            ->post(route('payment-account.statements.store', $account), [
                'files' => [$file],
                'period_year' => 2026,
                'period_month' => 9,
            ])
            ->assertSessionHas('success');

        $first = BankStatement::query()->where('payment_account_id', $account->id)->first();
        $this->assertNotNull($first);
        $this->assertSame(2, $first->lines()->count());
        $this->assertSame(0, (int) data_get($first->validation_summary, 'skipped_duplicates'));

        $firstLine = BankStatementLine::query()
            ->where('payment_account_id', $account->id)
            ->where('external_id', 'EXT-100')
            ->first();
        $this->assertNotNull($firstLine);
        $firstLine->forceFill([
            'payment_id' => null,
            'match_status' => 'matched',
            'description' => 'Already reconciled concept',
        ])->save();

        $reupload = UploadedFile::fake()->createWithContent('extracto-again.csv', $csv);

        $this->actingAs($user)
            ->post(route('payment-account.statements.store', $account), [
                'files' => [$reupload],
                'period_year' => 2026,
                'period_month' => 9,
            ])
            ->assertSessionHas('success');

        $this->assertSame(2, BankStatement::query()->where('payment_account_id', $account->id)->count());
        $this->assertSame(2, BankStatementLine::query()->where('payment_account_id', $account->id)->count());
        $this->assertSame(
            1,
            BankStatementLine::query()
                ->where('payment_account_id', $account->id)
                ->where('external_id', 'EXT-100')
                ->count(),
        );

        $second = BankStatement::query()
            ->where('payment_account_id', $account->id)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($second);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(0, $second->lines()->count());
        $this->assertSame(2, (int) data_get($second->validation_summary, 'skipped_duplicates'));

        $firstLine->refresh();
        $this->assertSame('Already reconciled concept', $firstLine->description);
        $this->assertSame('matched', $firstLine->match_status);
        $this->assertSame($first->id, (int) $firstLine->bank_statement_id);
    }

    public function test_same_external_id_can_exist_on_different_accounts(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;

        $rural = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'RURAL',
            'name' => 'Caja Rural',
            'currency_id' => 978,
            'status' => 1,
        ]);
        $stripe = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'STRIPE',
            'name' => 'Stripe',
            'currency_id' => 978,
            'status' => 1,
        ]);

        $csv = "DATE;AMOUNT;EXTERNAL_ID;REFERENCE\n2026-09-10;10.00;SHARED-1;same\n";

        $this->actingAs($user)
            ->post(route('payment-account.statements.store', $rural), [
                'files' => [UploadedFile::fake()->createWithContent('a.csv', $csv)],
                'period_year' => 2026,
                'period_month' => 9,
            ])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('payment-account.statements.store', $stripe), [
                'files' => [UploadedFile::fake()->createWithContent('b.csv', $csv)],
                'period_year' => 2026,
                'period_month' => 9,
            ])
            ->assertSessionHas('success');

        $this->assertSame(
            2,
            BankStatementLine::query()->where('external_id', 'SHARED-1')->count(),
        );
        $this->assertSame(
            1,
            BankStatementLine::query()
                ->where('payment_account_id', $rural->id)
                ->where('external_id', 'SHARED-1')
                ->count(),
        );
        $this->assertSame(
            1,
            BankStatementLine::query()
                ->where('payment_account_id', $stripe->id)
                ->where('external_id', 'SHARED-1')
                ->count(),
        );
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
