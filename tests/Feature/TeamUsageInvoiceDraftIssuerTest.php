<?php

namespace Tests\Feature;

use App\Enums\TeamBillingFrequency;
use App\Models\TeamUsageInvoice;
use App\Models\TeamUsageInvoiceAdjustment;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\Billing\AssistantSubscriptionService;
use App\Services\Billing\TeamUsageInvoiceDraftIssuer;
use App\Services\Billing\TeamUsageInvoiceStripeGateway;
use App\Support\TeamUsageInvoiceFrequency;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class TeamUsageInvoiceDraftIssuerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_stripe_draft_for_a_closed_month_and_does_not_finalize(): void
    {
        $team = $this->teamWithStripe();
        $this->createTokenLog((int) $team->id, 1_000_000, Carbon::parse('2026-08-15 10:00:00'));

        $this->mock(TeamUsageInvoiceStripeGateway::class, function ($mock)
        {
            $mock->shouldReceive('createDraftInvoice')
                ->once()
                ->andReturn((object) ['id' => 'in_draft_usage_1']);
            $mock->shouldReceive('addInvoiceItem')
                ->once()
                ->withArgs(function (string $customer, string $invoice, string $description, int $amount, string $currency): bool
                {
                    return $customer === 'cus_test_usage'
                        && $invoice === 'in_draft_usage_1'
                        && $description === 'Tokens IA · Agosto 2026 · 10.000.000'
                        && ! str_contains($description, 'envíos')
                        && $amount > 0
                        && $currency === 'EUR';
                })
                ->andReturn((object) ['id' => 'ii_usage_1']);
        });

        $results = app(TeamUsageInvoiceDraftIssuer::class)->issueDueDrafts(
            $team,
            false,
            Carbon::parse('2026-09-24 12:00:00'),
        );

        $drafts = $results->where('status', TeamUsageInvoice::STATUS_DRAFT);
        $this->assertCount(1, $drafts);
        $this->assertSame('2026-08-01', $drafts->first()['period_from']);
        $this->assertSame('2026-09-01', $drafts->first()['period_to']);
        $this->assertSame('in_draft_usage_1', $drafts->first()['stripe_invoice_id']);

        $this->assertDatabaseHas('team_usage_invoices', [
            'team_id' => $team->id,
            'kind' => TeamUsageInvoice::KIND_CYCLE,
            'status' => TeamUsageInvoice::STATUS_DRAFT,
            'stripe_invoice_id' => 'in_draft_usage_1',
        ]);
    }

    public function test_skips_zero_amount_windows_without_calling_stripe(): void
    {
        $team = $this->teamWithStripe();

        $this->mock(TeamUsageInvoiceStripeGateway::class, function ($mock)
        {
            $mock->shouldNotReceive('createDraftInvoice');
            $mock->shouldNotReceive('addInvoiceItem');
        });

        $results = app(TeamUsageInvoiceDraftIssuer::class)->issueDueDrafts(
            $team,
            false,
            Carbon::parse('2026-09-24 12:00:00'),
        );

        $this->assertTrue($results->every(fn (array $row): bool => $row['status'] === 'skipped'));
        $this->assertDatabaseCount('team_usage_invoices', 0);
    }

    public function test_skips_a_period_that_already_has_a_usage_invoice(): void
    {
        $team = $this->teamWithStripe();
        $this->createTokenLog((int) $team->id, 1_000_000, Carbon::parse('2026-08-15 10:00:00'));

        TeamUsageInvoice::factory()->create([
            'team_id' => $team->id,
            'period_from' => Carbon::parse('2026-08-01 00:00:00'),
            'period_to' => Carbon::parse('2026-09-01 00:00:00'),
            'stripe_invoice_id' => 'in_already',
        ]);

        $this->mock(TeamUsageInvoiceStripeGateway::class, function ($mock)
        {
            $mock->shouldNotReceive('createDraftInvoice');
            $mock->shouldNotReceive('addInvoiceItem');
        });

        $results = app(TeamUsageInvoiceDraftIssuer::class)->issueDueDrafts(
            $team,
            false,
            Carbon::parse('2026-09-24 12:00:00'),
        );

        $this->assertFalse($results->contains(fn (array $row): bool => $row['period_from'] === '2026-08-01'));
        $this->assertDatabaseCount('team_usage_invoices', 1);
    }

    public function test_dry_run_does_not_persist_or_call_stripe(): void
    {
        $team = $this->teamWithStripe();
        $this->createTokenLog((int) $team->id, 1_000_000, Carbon::parse('2026-08-15 10:00:00'));

        $this->mock(TeamUsageInvoiceStripeGateway::class, function ($mock)
        {
            $mock->shouldNotReceive('createDraftInvoice');
            $mock->shouldNotReceive('addInvoiceItem');
        });

        $results = app(TeamUsageInvoiceDraftIssuer::class)->issueDueDrafts(
            $team,
            true,
            Carbon::parse('2026-09-24 12:00:00'),
        );

        $this->assertTrue($results->contains(fn (array $row): bool => $row['status'] === 'dry-run' && $row['period_from'] === '2026-08-01'));
        $this->assertDatabaseCount('team_usage_invoices', 0);
    }

    public function test_creates_a_draft_for_a_pending_frequency_adjustment(): void
    {
        $team = $this->teamWithStripe();
        $this->createTokenLog((int) $team->id, 1_000_000, Carbon::parse('2026-09-10 10:00:00'));

        $adjustment = TeamUsageInvoiceAdjustment::factory()->create([
            'team_id' => $team->id,
            'frequency' => TeamBillingFrequency::Monthly,
            'period_from' => Carbon::parse('2026-09-01 00:00:00'),
            'period_to' => Carbon::parse('2026-09-15 00:00:00'),
        ]);

        $this->mock(TeamUsageInvoiceStripeGateway::class, function ($mock)
        {
            $mock->shouldReceive('createDraftInvoice')
                ->once()
                ->andReturn((object) ['id' => 'in_draft_adj_1']);
            $mock->shouldReceive('addInvoiceItem')
                ->once()
                ->andReturn((object) ['id' => 'ii_adj_1']);
        });

        $results = app(TeamUsageInvoiceDraftIssuer::class)->issueDueDrafts(
            $team,
            false,
            Carbon::parse('2026-09-24 12:00:00'),
        );

        $draft = $results->firstWhere('kind', TeamUsageInvoice::KIND_ADJUSTMENT);
        $this->assertNotNull($draft);
        $this->assertSame(TeamUsageInvoice::STATUS_DRAFT, $draft['status']);
        $this->assertSame('in_draft_adj_1', $draft['stripe_invoice_id']);

        $this->assertNotNull($adjustment->fresh()->invoiced_at);
        $this->assertDatabaseHas('team_usage_invoices', [
            'team_id' => $team->id,
            'kind' => TeamUsageInvoice::KIND_ADJUSTMENT,
            'adjustment_id' => $adjustment->id,
            'status' => TeamUsageInvoice::STATUS_DRAFT,
        ]);
    }

    public function test_creates_a_draft_for_a_closed_week(): void
    {
        $team = $this->teamWithStripe();
        $team->setSetting(TeamUsageInvoiceFrequency::SETTING_KEY, TeamBillingFrequency::Weekly->value, [
            'type' => 'string',
            'group' => 'billing',
        ]);
        $team->unsetRelation('settings');

        $this->createTokenLog((int) $team->id, 1_000_000, Carbon::parse('2026-09-16 10:00:00'));

        $this->mock(TeamUsageInvoiceStripeGateway::class, function ($mock)
        {
            $mock->shouldReceive('createDraftInvoice')
                ->once()
                ->andReturn((object) ['id' => 'in_draft_week_1']);
            $mock->shouldReceive('addInvoiceItem')
                ->once()
                ->andReturn((object) ['id' => 'ii_week_1']);
        });

        $results = app(TeamUsageInvoiceDraftIssuer::class)->issueDueDrafts(
            $team,
            false,
            Carbon::parse('2026-09-24 12:00:00'),
        );

        $draft = $results->firstWhere('status', TeamUsageInvoice::STATUS_DRAFT);
        $this->assertNotNull($draft);
        $this->assertSame('2026-09-14', $draft['period_from']);
        $this->assertSame('2026-09-21', $draft['period_to']);
        $this->assertSame('in_draft_week_1', $draft['stripe_invoice_id']);
    }

    public function test_artisan_command_dry_run_lists_the_closed_period(): void
    {
        $team = $this->teamWithStripe();
        $this->createTokenLog((int) $team->id, 1_000_000, Carbon::parse('2026-08-15 10:00:00'));

        Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00'));

        $this->artisan('billing:issue-usage-invoice-drafts', [
            '--team' => $team->id,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('dry-run')
            ->assertSuccessful();

        $this->assertDatabaseCount('team_usage_invoices', 0);

        Carbon::setTestNow();
    }

    public function test_artisan_command_prints_stripe_errors(): void
    {
        $team = $this->teamWithStripe();
        $this->createTokenLog((int) $team->id, 1_000_000, Carbon::parse('2026-08-15 10:00:00'));

        $this->mock(TeamUsageInvoiceStripeGateway::class, function ($mock)
        {
            $mock->shouldReceive('createDraftInvoice')
                ->once()
                ->andThrow(new \RuntimeException('Keys for idempotent requests can only be used once.'));
        });

        Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00'));

        $this->artisan('billing:issue-usage-invoice-drafts', [
            '--team' => $team->id,
        ])
            ->expectsOutputToContain('Team '.$team->id.': Keys for idempotent requests can only be used once.')
            ->assertFailed();

        Carbon::setTestNow();
    }

    public function test_deletes_orphan_stripe_draft_when_adding_items_fails(): void
    {
        $team = $this->teamWithStripe();
        $this->createTokenLog((int) $team->id, 1_000_000, Carbon::parse('2026-08-15 10:00:00'));

        $this->mock(TeamUsageInvoiceStripeGateway::class, function ($mock)
        {
            $mock->shouldReceive('createDraftInvoice')
                ->once()
                ->andReturn((object) ['id' => 'in_orphan_empty']);
            $mock->shouldReceive('addInvoiceItem')
                ->once()
                ->andThrow(new \RuntimeException('No such payment_method'));
            $mock->shouldReceive('deleteDraftInvoice')
                ->once()
                ->with('in_orphan_empty')
                ->andReturn((object) ['id' => 'in_orphan_empty', 'deleted' => true]);
        });

        $results = app(TeamUsageInvoiceDraftIssuer::class)->issueDueDrafts(
            $team,
            false,
            Carbon::parse('2026-09-24 12:00:00'),
        );

        $this->assertTrue($results->contains(fn (array $row): bool => $row['status'] === 'error'));
        $this->assertDatabaseCount('team_usage_invoices', 0);
    }

    public function test_discard_command_deletes_the_stripe_draft_and_humano_row(): void
    {
        $team = $this->teamWithStripe();

        TeamUsageInvoice::factory()->create([
            'team_id' => $team->id,
            'stripe_invoice_id' => 'in_wrong_window',
            'period_from' => Carbon::parse('2026-08-04 15:55:19'),
            'period_to' => Carbon::parse('2026-09-04 15:55:19'),
        ]);

        $this->mock(TeamUsageInvoiceStripeGateway::class, function ($mock)
        {
            $mock->shouldReceive('deleteDraftInvoice')
                ->once()
                ->with('in_wrong_window')
                ->andReturn((object) ['id' => 'in_wrong_window', 'deleted' => true]);
        });

        $this->artisan('billing:discard-usage-invoice-draft', [
            'invoice' => 'in_wrong_window',
        ])->assertSuccessful();

        $this->assertDatabaseMissing('team_usage_invoices', [
            'stripe_invoice_id' => 'in_wrong_window',
        ]);
    }

    public function test_due_jobs_use_assistant_day_when_first_plan_anchor_differs(): void
    {
        $team = $this->teamWithStripe();
        TeamUsageInvoiceFrequency::rememberCycleFromFirstSubscription(
            $team,
            Carbon::parse('2026-08-04 15:55:19'),
        );

        $this->mock(AssistantSubscriptionService::class, function ($mock)
        {
            $mock->shouldReceive('subscribedUsagePeriod')->andReturn([
                Carbon::parse('2026-08-24 19:33:08'),
                Carbon::parse('2026-09-24 19:33:08'),
            ]);
        });

        $jobs = app(TeamUsageInvoiceDraftIssuer::class)->dueJobs(
            $team,
            Carbon::parse('2026-09-24 12:10:22'),
        );

        $this->assertCount(1, $jobs);
        $this->assertSame('2026-07-24', $jobs[0]['from']->toDateString());
        $this->assertSame('2026-08-24', $jobs[0]['closes_on']->toDateString());
    }

    private function teamWithStripe(): \App\Models\Team
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->fakeTokenCatalog();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $team->forceFill(['stripe_id' => 'cus_test_usage'])->save();

        return $team->fresh();
    }

    private function fakeTokenCatalog(): void
    {
        config([
            'services.openrouter.cache_store' => 'array',
            'humano_pricing.token_billing.currency' => 'EUR',
            'humano_pricing.token_billing.client_token_multiplier' => 10,
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/models' => Http::response(['data' => []], 200),
        ]);
    }

    private function createTokenLog(int $teamId, int $tokens, \DateTimeInterface $at): void
    {
        $log = TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'module_id' => null,
            'service' => 'ContactSentimentAnalysisService',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => $tokens,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);
        $log->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }
}
