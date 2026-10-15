<?php

namespace Tests\Unit;

use App\Helpers\EmailTrackingHelper;
use Tests\TestCase;

class EmailTrackingHelperListUnsubscribeTest extends TestCase
{
    public function test_list_unsubscribe_headers_include_one_click_url(): void
    {
        config(['services.go.url' => 'https://go.idoneo.dev']);

        $headers = EmailTrackingHelper::listUnsubscribeHeaders('qa@example.test', true);

        $this->assertSame(
            '<'.EmailTrackingHelper::unsubscribeUrl('qa@example.test').'>',
            $headers['List-Unsubscribe'],
        );
        $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
        $this->assertStringContainsString('mailer.', $headers['List-Id']);
        $this->assertStringContainsString(
            'https://go.idoneo.dev/unsubscribe/'.rawurlencode('qa@example.test'),
            $headers['List-Unsubscribe'],
        );
    }

    public function test_open_and_click_tracking_urls_use_the_go_host(): void
    {
        config(['services.go.url' => 'https://go.idoneo.dev']);

        $this->assertSame(
            'https://go.idoneo.dev/unsubscribe/'.rawurlencode('qa@example.test'),
            EmailTrackingHelper::unsubscribeUrl('qa@example.test'),
        );

        $this->assertSame(
            'https://go.idoneo.dev/message/track/abc123',
            EmailTrackingHelper::openTrackingUrl('abc123'),
        );
        $this->assertSame(
            'https://go.idoneo.dev/message/track/click/abc123?url='.urlencode('https://www.pedimosfacil.com'),
            EmailTrackingHelper::clickTrackingUrl('abc123', 'https://www.pedimosfacil.com'),
        );
        $this->assertSame(
            'https://go.idoneo.dev/message/track/conversion',
            EmailTrackingHelper::conversionEndpoint(),
        );
    }

    public function test_list_unsubscribe_headers_are_empty_when_disabled_or_missing_email(): void
    {
        $this->assertSame([], EmailTrackingHelper::listUnsubscribeHeaders('qa@example.test', false));
        $this->assertSame([], EmailTrackingHelper::listUnsubscribeHeaders('  ', true));
        $this->assertSame([], EmailTrackingHelper::listUnsubscribeHeaders(null, true));
    }
}
