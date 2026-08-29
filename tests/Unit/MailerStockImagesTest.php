<?php

namespace Tests\Unit;

use App\Support\MailerStockImages;
use PHPUnit\Framework\TestCase;

class MailerStockImagesTest extends TestCase
{
    public function test_topic_picks_a_matching_stock_url(): void
    {
        $this->assertSame(MailerStockImages::PROMO, MailerStockImages::urlForTopic('promo de verano'));
        $this->assertSame(MailerStockImages::WELCOME, MailerStockImages::urlForTopic('Email de bienvenida'));
        $this->assertSame(MailerStockImages::EVENT, MailerStockImages::urlForTopic('Invitación al webinar'));
        $this->assertSame(MailerStockImages::REMINDER, MailerStockImages::urlForTopic('Recordatorio de turno'));
        $this->assertSame(MailerStockImages::NEWSLETTER, MailerStockImages::urlForTopic('Newsletter mensual'));
    }

    public function test_ensure_hero_injects_image_when_missing(): void
    {
        $html = MailerStockImages::ensureHero('<h2>Hola</h2><p>Texto</p>', 'bienvenida');

        $this->assertStringContainsString('photo-1521737604893-d14cc237f11d', $html);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('<h2>Hola</h2>', $html);
    }

    public function test_ensure_hero_keeps_existing_image(): void
    {
        $original = '<p><img src="https://picsum.photos/id/1015/1200/480" alt="Hero"></p><p>Cuerpo</p>';

        $this->assertSame($original, MailerStockImages::ensureHero($original, 'promo'));
    }
}
