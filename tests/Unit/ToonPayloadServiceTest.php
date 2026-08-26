<?php

namespace Tests\Unit;

use App\Services\ToonPayloadService;
use Tests\TestCase;

class ToonPayloadServiceTest extends TestCase
{
    public function test_encode_compact_list_saves_tokens_against_json(): void
    {
        $rows = [];
        for ($i = 1; $i <= 8; $i++)
        {
            $rows[] = [
                'id' => $i,
                'code' => 'SKU-'.$i,
                'name' => 'Producto de catálogo número '.$i,
                'category' => 'Ropa',
                'price' => '€ '.number_format(10 + $i, 2),
            ];
        }

        $encoded = ToonPayloadService::encode(['products' => $rows]);

        $this->assertTrue($encoded['used_toon']);
        $this->assertNotSame('', $encoded['text']);
        $this->assertStringContainsString('Producto de catálogo número 1', $encoded['text']);
        $this->assertGreaterThan(0, $encoded['tokens_saved']);
        $this->assertLessThan($encoded['json_tokens'], $encoded['toon_tokens']);
        $this->assertGreaterThan(0, $encoded['savings_percentage']);
    }

    public function test_merge_accumulates_savings(): void
    {
        $first = ToonPayloadService::encode(['contacts' => [
            ['id' => 1, 'name' => 'Ana Pérez', 'email' => 'ana@example.com'],
            ['id' => 2, 'name' => 'Luis Gómez', 'email' => 'luis@example.com'],
        ]]);
        $merged = ToonPayloadService::merge(ToonPayloadService::emptyMetrics(), $first);

        $this->assertSame($first['used_toon'], $merged['used_toon']);
        $this->assertSame($first['tokens_saved'], $merged['tokens_saved']);
        $this->assertSame($first['json_tokens'], $merged['json_tokens']);
    }
}
