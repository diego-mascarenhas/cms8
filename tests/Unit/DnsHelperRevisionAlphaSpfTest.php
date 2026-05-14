<?php

namespace Tests\Unit;

use App\Helpers\DnsHelper;
use PHPUnit\Framework\TestCase;

class DnsHelperRevisionAlphaSpfTest extends TestCase
{
    public function test_exact_canonical_spf_is_accepted(): void
    {
        $this->assertTrue(DnsHelper::revisionAlphaSpfIsCanonical('v=spf1 include:spf.revisionalpha.com -all'));
    }

    public function test_whitespace_variants_are_accepted(): void
    {
        $this->assertTrue(DnsHelper::revisionAlphaSpfIsCanonical('v=spf1  include:spf.revisionalpha.com   -all'));
    }

    public function test_case_insensitive_match(): void
    {
        $this->assertTrue(DnsHelper::revisionAlphaSpfIsCanonical('V=spf1 INCLUDE:spf.revisionalpha.com -ALL'));
    }

    public function test_soft_fail_all_is_rejected(): void
    {
        $this->assertFalse(DnsHelper::revisionAlphaSpfIsCanonical('v=spf1 include:spf.revisionalpha.com ~all'));
    }

    public function test_extra_mechanisms_are_rejected(): void
    {
        $this->assertFalse(DnsHelper::revisionAlphaSpfIsCanonical('v=spf1 a mx include:spf.revisionalpha.com -all'));
    }

    public function test_other_mailbaby_include_without_revisionalpha_is_rejected(): void
    {
        $this->assertFalse(DnsHelper::revisionAlphaSpfIsCanonical('v=spf1 include:mail.baby -all'));
    }
}
