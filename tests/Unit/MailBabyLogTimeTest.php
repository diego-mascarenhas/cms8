<?php

namespace Tests\Unit;

use App\Services\MailBabyService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MailBabyLogTimeTest extends TestCase
{
    public function test_log_created_time_is_read_as_us_eastern(): void
    {
        config([
            'services.mailbaby.api_key' => 'test-key',
            'services.mailbaby.api_url' => 'https://api.mailbaby.net',
        ]);

        Http::fake([
            'https://api.mailbaby.net/mail/log*' => Http::response([
                'emails' => [[
                    'id' => '1a0b9f105f6000dfc3',
                    'delivered' => 0,
                    'created' => '2026-10-15 04:53:00',
                    'from' => 'info@invigilando.com',
                    'to' => 'info@invigilando.com',
                ]],
            ]),
        ]);

        $record = app(MailBabyService::class)->findDeliveryRecord('1a0b9f105f6000dfc3', null, null, null);

        $this->assertSame('2026-10-15T04:53:00-04:00', $record['created'] ?? null);
    }
}
