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

    public function test_mailbaby_user_record_accepts_one_or_more_usernames(): void
    {
        $this->assertTrue(DnsHelper::mailbabyUserRecordMatches('mb80474'));
        $this->assertTrue(DnsHelper::mailbabyUserRecordMatches('mb80147 mb80474'));
        $this->assertTrue(DnsHelper::mailbabyUserRecordMatches('v=1 user=mb80474'));
        $this->assertFalse(DnsHelper::mailbabyUserRecordMatches('80474'));
        $this->assertFalse(DnsHelper::mailbabyUserRecordMatches(''));
    }
}
