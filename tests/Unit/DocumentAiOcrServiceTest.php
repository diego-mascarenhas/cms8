<?php

namespace Tests\Unit;

use App\Services\DocumentAiOcrService;
use Tests\TestCase;

class DocumentAiOcrServiceTest extends TestCase
{
    public function test_defaults_to_gpt_4o_mini_for_photos(): void
    {
        $this->assertSame('openai/gpt-4o-mini', app(DocumentAiOcrService::class)->resolveOcrModel());
        $this->assertSame('anthropic/claude-haiku-4.5', app(DocumentAiOcrService::class)->resolveOcrFailoverModel());
    }

    public function test_uses_the_configured_ocr_model(): void
    {
        config(['ai.ocr_model' => 'anthropic/claude-haiku-4.5']);

        $this->assertSame('anthropic/claude-haiku-4.5', app(DocumentAiOcrService::class)->resolveOcrModel());
    }
}
