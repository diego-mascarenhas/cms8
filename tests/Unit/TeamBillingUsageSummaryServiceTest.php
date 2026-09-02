<?php

namespace Tests\Unit;

use App\Enums\TeamBillingFrequency;
use App\Models\Conversation;
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
        $this->assertSame('1.000.000 → 10.000.000', $current['formatted']['tokens']);
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
        $this->assertSame('06/09/2026', $preview['closes_on']);
        $this->assertSame('Semana del 31 de agosto al 6 de septiembre 2026', $preview['period_label']);

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
