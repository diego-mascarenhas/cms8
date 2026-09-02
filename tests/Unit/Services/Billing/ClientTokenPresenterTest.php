<?php

namespace Tests\Unit\Services\Billing;

use App\Services\Billing\ClientTokenPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientTokenPresenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openrouter.cache_store' => 'array',
            'humano_pricing.token_billing.base_currency' => 'USD',
            'humano_pricing.token_billing.currency' => 'USD',
            'humano_pricing.token_billing.client_token_multiplier' => 2,
            'ai.assistant_model' => 'cheapest',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/models' => Http::response(['data' => []], 200),
        ]);
    }

    public function test_presents_double_tokens_at_catalog_market_rates(): void
    {
        $presented = app(ClientTokenPresenter::class)->present(
            100_000,
            20_000,
            'claude-haiku-4-5-20251001',
        );

        $this->assertSame(200_000, $presented['prompt_tokens']);
        $this->assertSame(40_000, $presented['completion_tokens']);
        $this->assertSame(240_000, $presented['total_tokens']);
        $this->assertSame(40, $presented['amount_cents']);
    }

    public function test_unknown_model_falls_back_to_default_haiku_catalog(): void
    {
        $presented = app(ClientTokenPresenter::class)->present(1_000_000, 0, null);

        $this->assertSame(2_000_000, $presented['total_tokens']);
        $this->assertSame(200, $presented['amount_cents']);
        $this->assertSame('claude-haiku-4.5', app(ClientTokenPresenter::class)->billingModel(null));
    }

    public function test_whisper_uses_catalog_fallback(): void
    {
        $presented = app(ClientTokenPresenter::class)->present(10_000, 0, 'whisper-1');

        $this->assertSame(20_000, $presented['total_tokens']);
        $this->assertSame(12, $presented['amount_cents']);
    }
}
