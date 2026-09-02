<?php

namespace Tests\Unit;

use App\Services\DocumentAiOcrService;
use Tests\TestCase;

class DocumentAiOcrServiceTest extends TestCase
{
    public function test_defaults_to_haiku_for_photos(): void
    {
        config(['ai.ocr_model' => 'anthropic/claude-haiku-4.5']);

        $this->assertSame('anthropic/claude-haiku-4.5', app(DocumentAiOcrService::class)->resolveOcrModel());
    }

    public function test_uses_the_configured_ocr_model(): void
    {
        config(['ai.ocr_model' => 'openai/gpt-4o-mini']);

        $this->assertSame('openai/gpt-4o-mini', app(DocumentAiOcrService::class)->resolveOcrModel());
    }
}
