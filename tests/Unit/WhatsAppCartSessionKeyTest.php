<?php

namespace Tests\Unit;

use App\Helpers\WhatsAppCartSessionKey;
use PHPUnit\Framework\TestCase;

class WhatsAppCartSessionKeyTest extends TestCase
{
    public function test_spain_nine_digit_national_maps_to_thirty_four_prefix(): void
    {
        $this->assertSame('34600000000', WhatsAppCartSessionKey::fromPhone('600000000'));
        $this->assertSame('34600000000', WhatsAppCartSessionKey::fromPhone('+34 600 000 000'));
    }

    public function test_eleven_digit_spanish_unchanged(): void
    {
        $this->assertSame('34600000000', WhatsAppCartSessionKey::fromPhone('34600000000'));
    }
}
