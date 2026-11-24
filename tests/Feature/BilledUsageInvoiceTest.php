<?php

namespace Tests\Feature;

use App\Enums\TeamBillingProduct;
use App\Models\TeamBillingRate;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\TeamBillingUsageSummaryService;
use App\Services\TokenBillingRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BilledUsageInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_billed_usage_keeps_the_multiplier_from_the_call(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);

        $multiplier = TokenBillingRateService::clientTokenMultiplier($team);
        TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => null,
            'service' => 'PromptController',
            'json_size' => 40,
            'toon_size' => 0,
            'json_tokens' => 80,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);

        $log = TokenUsageLog::withoutGlobalScopes()->first();
        $expectedLog = (int) round(80 * $multiplier);

        $this->assertSame($expectedLog, (int) $log->billed_usage['prompt_tokens']);
        $this->assertSame(0, (int) $log->billed_usage['completion_tokens']);
        $this->assertSame(0, (int) $log->billed_usage['tokens_saved']);

        $before = app(TeamBillingUsageSummaryService::class)->invoicePreview($team);
        TeamBillingRate::setAmount((int) $team->id, TeamBillingProduct::TokensMultiplier, $multiplier + 7);

        $after = app(TeamBillingUsageSummaryService::class)->invoicePreview($team->fresh());

        $this->assertSame($expectedLog, (int) $before['tokens_billed']);
        $this->assertSame((int) $before['tokens_billed'], (int) $after['tokens_billed']);
        $this->assertNotSame($multiplier, TokenBillingRateService::clientTokenMultiplier($team->fresh()));
    }
}
