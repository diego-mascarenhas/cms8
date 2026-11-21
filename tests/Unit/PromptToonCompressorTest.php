<?php

namespace Tests\Unit;

use App\Services\PromptToonCompressor;
use Tests\TestCase;

class PromptToonCompressorTest extends TestCase
{
    public function test_plain_text_stays_uncompressed(): void
    {
        $result = PromptToonCompressor::compress('Escribí un título corto para la propuesta.');

        $this->assertSame('Escribí un título corto para la propuesta.', $result['prompt']);
        $this->assertFalse($result['toon']['used_toon']);
        $this->assertSame(0, $result['toon']['tokens_saved']);
    }

    public function test_a_repeated_json_list_is_replaced_with_toon(): void
    {
        $rows = [
            ['name' => 'Costo', 'detail' => 'El presupuesto inicial es alto para el primer trimestre del proyecto'],
            ['name' => 'Plazo', 'detail' => 'La ventana de implementación supera los seis meses previstos'],
        ];
        $prompt = "Evaluá estos riesgos:\n```json\n".json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n```\n";

        $result = PromptToonCompressor::compress($prompt);

        $this->assertTrue($result['toon']['used_toon']);
        $this->assertGreaterThan(0, $result['toon']['tokens_saved']);
        $this->assertStringContainsString('```toon', $result['prompt']);
        $this->assertStringNotContainsString('```json', $result['prompt']);
        $this->assertStringContainsString('Evaluá estos riesgos:', $result['prompt']);
    }

    public function test_a_json_block_that_does_not_shrink_stays_as_json(): void
    {
        $prompt = "Dato:\n```json\n".json_encode([1])."\n```\n";

        $result = PromptToonCompressor::compress($prompt);

        $this->assertFalse($result['toon']['used_toon']);
        $this->assertStringContainsString('```json', $result['prompt']);
    }
}
