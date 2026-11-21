<?php

namespace Tests\Feature;

use App\Enums\TeamBillingProduct;
use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\TeamBillingRate;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\Billing\McpClientDiscount;
use App\Services\TeamBillingUsageSummaryService;
use App\Services\TokenBillingRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BilledUsageInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        McpClientDiscount::freeze(null);

        parent::tearDown();
    }

    public function test_billed_usage_keeps_the_multiplier_from_the_call(): void
    {
        McpClientDiscount::freeze(40);

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

        $conversationId = (string) Str::uuid();
        AgentConversation::query()->create([
            'id' => $conversationId,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'title' => 'Factura',
        ]);
        AgentConversationMessage::query()->create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'agent' => 'chat_assistant',
            'role' => 'assistant',
            'content' => 'Listo',
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [
                'prompt_tokens' => 80,
                'completion_tokens' => 20,
                'total_tokens' => 100,
                'model' => 'claude-haiku-4-5-20251001',
            ],
            'meta' => [
                'model' => 'claude-haiku-4-5-20251001',
                'provider' => 'anthropic',
            ],
        ]);

        $log = TokenUsageLog::withoutGlobalScopes()->first();
        $message = AgentConversationMessage::query()->first();
        $expectedLog = (int) round(80 * $multiplier);
        $expectedPrompt = (int) round(48 * $multiplier);
        $expectedCompletion = (int) round(12 * $multiplier);
        $expectedSaved = (int) round(40 * $multiplier);

        $this->assertSame($expectedLog, (int) $log->billed_usage['prompt_tokens']);
        $this->assertSame(0, (int) $log->billed_usage['completion_tokens']);
        $this->assertSame(0, (int) $log->billed_usage['tokens_saved']);
        $this->assertSame($expectedPrompt, (int) $message->billed_usage['prompt_tokens']);
        $this->assertSame($expectedCompletion, (int) $message->billed_usage['completion_tokens']);
        $this->assertSame($expectedSaved, (int) $message->billed_usage['tokens_saved']);
        $this->assertSame('claude-haiku-4-5-20251001', $message->billed_usage['model']);

        $before = app(TeamBillingUsageSummaryService::class)->invoicePreview($team);
        TeamBillingRate::setAmount((int) $team->id, TeamBillingProduct::TokensMultiplier, $multiplier + 7);

        $after = app(TeamBillingUsageSummaryService::class)->invoicePreview($team->fresh());

        $this->assertSame($expectedLog + $expectedPrompt + $expectedCompletion, (int) $before['tokens_billed']);
        $this->assertSame((int) $before['tokens_billed'], (int) $after['tokens_billed']);
        $this->assertNotSame($multiplier, TokenBillingRateService::clientTokenMultiplier($team->fresh()));
    }
}
