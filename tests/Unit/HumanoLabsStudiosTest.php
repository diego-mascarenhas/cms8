<?php

namespace Tests\Unit;

use App\Support\HumanoLabsStudios;
use PHPUnit\Framework\TestCase;

class HumanoLabsStudiosTest extends TestCase
{
    public function test_catalog_lists_the_seven_labs_studios(): void
    {
        $keys = array_column(HumanoLabsStudios::all(), 'key');

        $this->assertSame(
            ['signage', 'hosting', 'design', 'marketing', 'innovation', 'development', 'consulting'],
            $keys,
        );
        $this->assertStringContainsString('Diseño, Desarrollo', HumanoLabsStudios::offerLine());
        $this->assertStringContainsString('https://mixvasallo.com', HumanoLabsStudios::promptBlock());
        $this->assertStringContainsString('https://globaltraffic.com', HumanoLabsStudios::promptBlock());
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function studioPhrases(): array
    {
        return [
            ['necesitamos senaletica para el local', 'signage', 'https://globaltraffic.com'],
            ['el hosting se cae y el cpanel no responde', 'hosting', 'https://revisionalpha.com'],
            ['hace falta diseno de marca y un logo', 'design', 'https://mixvasallo.com'],
            ['queremos un cambio de imagen', 'design', 'https://mixvasallo.com'],
            ['es de la marca', 'design', 'https://mixvasallo.com'],
            ['es del negocio', 'consulting', 'https://humano.app'],
            ['queremos marketing y campanas', 'marketing', 'https://somosconga.com'],
            ['un sistema de innovacion tipo fanyion', 'innovation', 'https://fanyion.com'],
            ['necesitamos desarrollo de software a medida', 'development', 'https://idoneo.dev'],
            ['hay desorden y no logramos crecer', 'consulting', 'https://humano.app'],
        ];
    }

    /**
     * @dataProvider studioPhrases
     */
    public function test_match_prefers_the_studio_for_that_problem(string $phrase, string $key, string $url): void
    {
        $studio = HumanoLabsStudios::match($phrase);

        $this->assertNotNull($studio);
        $this->assertSame($key, $studio['key']);
        $this->assertSame($url, $studio['url']);
    }

    public function test_signage_wins_over_consulting_when_both_appear(): void
    {
        $studio = HumanoLabsStudios::match('hay desorden y tambien senaletica digital');

        $this->assertSame('signage', $studio['key'] ?? null);
    }
}
