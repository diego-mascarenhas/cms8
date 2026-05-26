<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncPresentationWhatsappQrCommandTest extends TestCase
{
    public function test_sync_command_saves_qr_png_for_presentation(): void
    {
        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        ).str_repeat('x', 120);

        Http::fake([
            'http://wa.test/qr.png*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $target = public_path('homes/humano/img/presentations/whatsapp-qr.png');
        if (is_file($target))
        {
            unlink($target);
        }

        $this->artisan('humano:sync-presentation-whatsapp-qr', ['--team' => 1])
            ->assertSuccessful();

        $this->assertFileExists($target);
        $this->assertGreaterThan(50, filesize($target));

        $presentationHtml = file_get_contents(public_path('homes/humano/presentations/chat-contactos-modulos.html'));
        $this->assertIsString($presentationHtml);
        $this->assertStringContainsString('data:image/png;base64,', $presentationHtml);

        unlink($target);
    }
}
