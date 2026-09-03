<?php

namespace Tests\Unit;

use App\Helpers\EmailTrackingHelper;
use Tests\TestCase;

class EmailTrackingHelperListUnsubscribeTest extends TestCase
{
    public function test_list_unsubscribe_headers_include_one_click_url(): void
    {
        $headers = EmailTrackingHelper::listUnsubscribeHeaders('qa@example.test', true);

        $this->assertSame(
            '<'.EmailTrackingHelper::unsubscribeUrl('qa@example.test').'>',
            $headers['List-Unsubscribe'],
        );
        $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
        $this->assertStringContainsString('mailer.', $headers['List-Id']);
        $this->assertStringContainsString(rawurlencode('qa@example.test'), $headers['List-Unsubscribe']);
    }

    public function test_list_unsubscribe_headers_are_empty_when_disabled_or_missing_email(): void
    {
        $this->assertSame([], EmailTrackingHelper::listUnsubscribeHeaders('qa@example.test', false));
        $this->assertSame([], EmailTrackingHelper::listUnsubscribeHeaders('  ', true));
        $this->assertSame([], EmailTrackingHelper::listUnsubscribeHeaders(null, true));
    }
}
