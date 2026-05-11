<?php

namespace Tests\Unit;

use App\Helpers\DnsHelper;
use PHPUnit\Framework\TestCase;

class DnsHelperCanSendBroadcastFromUiTest extends TestCase
{
    public function test_bypass_when_treat_as_local_true(): void
    {
        $this->assertTrue(DnsHelper::canSendBroadcastFromUi(null, true, true));
    }

    public function test_system_smtp_without_dns_when_not_local(): void
    {
        $this->assertFalse(DnsHelper::canSendBroadcastFromUi(null, true, false));
    }

    public function test_own_smtp_allows_without_dns(): void
    {
        $this->assertTrue(DnsHelper::canSendBroadcastFromUi(null, false, false));
    }

    public function test_system_smtp_with_authorized_dns(): void
    {
        $dns = [
            'spf' => ['has_mailbaby' => true],
            'mailbaby_auth' => ['authorized' => true],
        ];

        $this->assertTrue(DnsHelper::canSendBroadcastFromUi($dns, true, false));
    }
}
