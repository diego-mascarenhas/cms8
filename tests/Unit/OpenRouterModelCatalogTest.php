<?php

namespace Tests\Unit;

use App\Services\OpenRouterModelCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterModelCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openrouter.cache_store' => 'array']);
        Http::fake([
            'https://openrouter.ai/api/v1/models' => Http::response(['data' => []], 200),
        ]);
    }

    public function test_matches_anthropic_snapshot_ids_to_haiku_fallback(): void
    {
        $match = app(OpenRouterModelCatalog::class)->find('claude-haiku-4-5-20251001');

        $this->assertNotNull($match);
        $this->assertSame(1.0, $match['prompt_per_million']);
        $this->assertSame(5.0, $match['completion_per_million']);
    }

    public function test_normalizes_provider_prefix_and_date_suffix(): void
    {
        $catalog = app(OpenRouterModelCatalog::class);

        $this->assertSame('claude-haiku-4.5', $catalog->normalizeKey('anthropic/claude-haiku-4-5-20251001'));
        $this->assertSame('whisper-1', $catalog->normalizeKey('openai/whisper-1:batch'));
    }
}
