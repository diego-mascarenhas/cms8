<?php

namespace Tests\Unit;

use App\Enums\TeamBillingFrequency;
use App\Models\Conversation;
use App\Models\Module;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\TeamBillingUsageSummaryService;
use App\Support\TeamUsageInvoiceFrequency;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class TeamBillingUsageSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_month_separates_token_cost_from_billed_markup(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->fakeTokenCatalog();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);

        $this->createTokenLog((int) $team->id, 1_000_000, now());
        $this->createTokenLog((int) $team->id, 500_000, now()->subMonth());

        $teamNumber = '34999000111';
        $team->setSetting('whatsapp_from', $teamNumber);
        $this->createOutboundWhatsApp($teamNumber, now());
        $this->createOutboundWhatsApp($teamNumber, now()->subMonth());

        $current = app(TeamBillingUsageSummaryService::class)->currentMonth($team);

        $this->assertTrue($current['is_current']);
        $this->assertSame(1_000_000, $current['tokens_real']);
        $this->assertSame(10_000_000, $current['tokens_billed']);
        $this->assertSame(100, $current['token_cost_cents']);
        $this->assertSame(1000, $current['token_billed_cents']);
        $this->assertSame(900, $current['token_markup_cents']);
        $this->assertSame(1, $current['whatsapp_messages']);
        $this->assertSame(0, $current['mailer_overage']);
        $this->assertSame(100, $current['cost_cents']);
        $this->assertSame(1000 + $current['whatsapp_billed_cents'], $current['billed_cents']);
        $this->assertSame(900, $current['markup_cents']);
        $this->assertSame('EUR', $current['currency']);
        $this->assertSame('10.000.000', $current['formatted']['tokens']);
        $this->assertSame('1,00 EUR', $current['formatted']['cost']);
        $this->assertSame('9,00 EUR', $current['formatted']['markup']);
    }

    public function test_invoice_preview_uses_weekly_window_when_frequency_is_weekly(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->fakeTokenCatalog();

        Carbon::setTestNow(Carbon::parse('2026-09-02 12:00:00'));

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);

        TeamUsageInvoiceFrequency::set($team, TeamBillingFrequency::Weekly);

        $this->createTokenLog((int) $team->id, 1_000_000, now());
        $this->createTokenLog((int) $team->id, 2_000_000, now()->startOfWeek(Carbon::MONDAY)->subDay());

        $preview = app(TeamBillingUsageSummaryService::class)->invoicePreview($team);

        $this->assertSame(TeamBillingFrequency::Weekly->value, $preview['frequency']);
        $this->assertSame('Semanal', $preview['frequency_label']);
        $this->assertSame(1_000_000, $preview['tokens_real']);
        $this->assertSame(1000, $preview['token_billed_cents']);
        $this->assertSame('09/09/2026', $preview['closes_on']);
        $this->assertSame('Semana del 2 al 9 de septiembre 2026', $preview['period_label']);
        $this->assertFalse($preview['has_adjustments']);

        Carbon::setTestNow();
    }

    public function test_switching_to_weekly_mid_month_opens_an_adjustment_for_elapsed_weeks(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->fakeTokenCatalog();

        Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00'));

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);

        $this->createTokenLog((int) $team->id, 1_000_000, Carbon::parse('2026-09-10 10:00:00'));
        $this->createTokenLog((int) $team->id, 500_000, Carbon::parse('2026-09-23 10:00:00'));

        TeamUsageInvoiceFrequency::set($team, TeamBillingFrequency::Weekly);

        $preview = app(TeamBillingUsageSummaryService::class)->invoicePreview($team);

        $this->assertTrue($preview['has_adjustments']);
        $this->assertCount(1, $preview['adjustments']);
        $this->assertSame(1_000_000, $preview['adjustments'][0]['tokens_real']);
        $this->assertSame(1000, $preview['adjustments'][0]['token_billed_cents']);
        $this->assertSame('1 al 22 de septiembre 2026', $preview['adjustments'][0]['period_label']);
        $this->assertSame(500_000, $preview['tokens_real']);
        $this->assertSame(500, $preview['token_billed_cents']);
        $this->assertSame('Semana del 23 al 30 de septiembre 2026', $preview['period_label']);
        $this->assertSame(1500, $preview['total_billed_cents']);
        $this->assertSame('15,00 EUR', $preview['formatted']['total_billed']);
        $this->assertSame('Tokens IA · 1 al 22 de septiembre 2026', $preview['invoice_lines'][0]['description']);
        $this->assertSame('10,00 EUR', $preview['invoice_lines'][0]['formatted_amount']);
        $this->assertSame('Tokens IA · Semana del 23 al 30 de septiembre 2026', $preview['invoice_lines'][3]['description']);
        $this->assertSame('5,00 EUR', $preview['invoice_lines'][3]['formatted_amount']);
        $this->assertSame('15.000.000', $preview['formatted']['total_tokens']);

        Carbon::setTestNow();
    }

    public function test_switching_to_monthly_mid_week_anchors_the_month_to_the_change_day(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->fakeTokenCatalog();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);

        Carbon::setTestNow(Carbon::parse('2026-09-02 12:00:00'));
        TeamUsageInvoiceFrequency::set($team, TeamBillingFrequency::Weekly);

        $this->createTokenLog((int) $team->id, 1_000_000, Carbon::parse('2026-09-10 10:00:00'));
        $this->createTokenLog((int) $team->id, 500_000, Carbon::parse('2026-09-15 10:00:00'));

        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00'));
        TeamUsageInvoiceFrequency::set($team, TeamBillingFrequency::Monthly);

        $preview = app(TeamBillingUsageSummaryService::class)->invoicePreview($team);

        $this->assertTrue($preview['has_adjustments']);
        $this->assertSame(TeamBillingFrequency::Monthly->value, $preview['frequency']);
        $this->assertSame('15 de septiembre al 15 de octubre 2026', $preview['period_label']);
        $this->assertSame('15/10/2026', $preview['closes_on']);
        $this->assertSame(1_000_000, $preview['adjustments'][0]['tokens_real']);
        $this->assertSame(500_000, $preview['tokens_real']);
        $this->assertSame(15, TeamUsageInvoiceFrequency::anchorDay($team));
        $this->assertSame('Tokens IA · 15 de septiembre al 15 de octubre 2026', $preview['invoice_lines'][3]['description']);

        Carbon::setTestNow();
    }

    public function test_invoice_preview_breaks_tokens_down_by_module(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->fakeTokenCatalog();

        Carbon::setTestNow(Carbon::parse('2026-09-02 12:00:00'));

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);

        $chat = Module::query()->firstOrCreate(
            ['key' => 'chat'],
            ['name' => 'Chat', 'is_core' => true, 'order' => 1, 'status' => 1],
        );
        $projects = Module::query()->firstOrCreate(
            ['key' => 'projects'],
            ['name' => 'Projects', 'is_core' => true, 'order' => 2, 'status' => 1],
        );
        $insights = Module::query()->firstOrCreate(
            ['key' => 'insights'],
            ['name' => 'Insights', 'is_core' => false, 'order' => 3, 'status' => 1],
        );

        $this->createTokenLog((int) $team->id, 8_000, now(), $chat->id);
        $this->createTokenLog((int) $team->id, 3_000, now(), $projects->id);
        $this->createTokenLog((int) $team->id, 1_000, now(), $insights->id);

        $preview = app(TeamBillingUsageSummaryService::class)->invoicePreview($team);

        $this->assertSame(12_000, $preview['tokens_real']);
        $this->assertSame(120_000, $preview['tokens_billed']);
        $this->assertSame('120.000', $preview['formatted']['tokens']);
        $this->assertSame('120.000', $preview['formatted']['total_tokens']);
        $this->assertSame('Chat 80.000 · Projects 30.000 · Insights 10.000', $preview['formatted']['tokens_by_module']);
        $this->assertSame('Chat', $preview['tokens_by_module'][0]['name']);
        $this->assertSame(8_000, $preview['tokens_by_module'][0]['tokens_real']);
        $this->assertSame(80_000, $preview['tokens_by_module'][0]['tokens_billed']);
        $this->assertSame('Projects', $preview['tokens_by_module'][1]['name']);
        $this->assertSame('Insights', $preview['tokens_by_module'][2]['name']);
        $this->assertSame('tokens', $preview['invoice_lines'][0]['kind']);
        $this->assertSame('120.000', $preview['invoice_lines'][0]['detail']);
        $this->assertSame('token_source', $preview['invoice_lines'][1]['kind']);
        $this->assertSame('Chat', $preview['invoice_lines'][1]['description']);
        $this->assertSame('80.000', $preview['invoice_lines'][1]['detail']);
        $this->assertSame('Projects', $preview['invoice_lines'][2]['description']);
        $this->assertSame('Insights', $preview['invoice_lines'][3]['description']);

        $change = app(TeamBillingUsageSummaryService::class)->simulateFrequencyChange($team, TeamBillingFrequency::Weekly);
        $this->assertNotNull($change);
        $this->assertSame('120.000', $change['formatted_tokens']);
        $weekly = collect($change['invoices'])->last();
        $this->assertSame('Chat', $weekly['lines'][1]['description']);
        $this->assertSame('80.000', $weekly['lines'][1]['detail']);

        Carbon::setTestNow();
    }

    public function test_past_months_exclude_the_current_month_and_empty_months(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->fakeTokenCatalog();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);

        $this->createTokenLog((int) $team->id, 1_000_000, now());
        $this->createTokenLog((int) $team->id, 2_000_000, now()->subMonth());

        $past = app(TeamBillingUsageSummaryService::class)->pastMonths($team, 2);

        $this->assertCount(1, $past);
        $this->assertFalse($past[0]['is_current']);
        $this->assertSame(now()->subMonth()->format('Y-m'), $past[0]['month']);
        $this->assertSame(2_000_000, $past[0]['tokens_real']);
        $this->assertSame(20_000_000, $past[0]['tokens_billed']);
        $this->assertSame(200, $past[0]['token_cost_cents']);
        $this->assertSame(2000, $past[0]['token_billed_cents']);
        $this->assertSame(1800, $past[0]['token_markup_cents']);
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

    private function createTokenLog(int $teamId, int $tokens, \DateTimeInterface $at, ?int $moduleId = null): void
    {
        $log = TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'module_id' => $moduleId,
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

    private function createOutboundWhatsApp(string $from, \DateTimeInterface $at): void
    {
        $message = Conversation::query()->create([
            'message_sid' => 'SM_'.uniqid(),
            'channel' => 'whatsapp',
            'from' => $from,
            'to' => '34600111222',
            'body' => 'Hola',
            'status' => 'sent',
            'direction' => 'outbound',
        ]);
        $message->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }
}
