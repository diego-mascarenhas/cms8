<?php

namespace Tests\Unit;

use App\Helpers\DnsHelper;
use PHPUnit\Framework\TestCase;

class DnsHelperRevisionAlphaSpfTest extends TestCase
{
    public function test_direct_include_is_detected(): void
    {
        $this->assertTrue(DnsHelper::spfIncludesRevisionAlpha('v=spf1 include:spf.revisionalpha.com -all'));
    }

    public function test_extra_mechanisms_with_include_passes(): void
    {
        $this->assertTrue(DnsHelper::spfIncludesRevisionAlpha(
            'v=spf1 a mx include:spf.revisionalpha.com include:_spf.mlsend.com ~all',
        ));
    }

    public function test_case_insensitive_include(): void
    {
        $this->assertTrue(DnsHelper::spfIncludesRevisionAlpha('V=spf1 INCLUDE:spf.revisionalpha.com -all'));
    }

    public function test_missing_include_fails(): void
    {
        $this->assertFalse(DnsHelper::spfIncludesRevisionAlpha('v=spf1 include:mail.baby -all'));
    }
}
