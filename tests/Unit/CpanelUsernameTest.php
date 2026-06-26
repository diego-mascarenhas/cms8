<?php

namespace Tests\Unit;

use App\Support\CpanelUsername;
use PHPUnit\Framework\TestCase;

class CpanelUsernameTest extends TestCase
{
    public function test_suggests_username_from_domain_label(): void
    {
        $this->assertSame('ejemplo', CpanelUsername::suggestFromDomain('ejemplo.com'));
        $this->assertSame('democpanelrevisi', CpanelUsername::suggestFromDomain('demo-cpanelrevisionalpha.net'));
    }

    public function test_suggests_username_prefixes_when_starts_with_number(): void
    {
        $this->assertSame('u123', CpanelUsername::suggestFromDomain('123.example.com'));
    }

    public function test_validates_cpanel_username_rules(): void
    {
        $this->assertTrue(CpanelUsername::isValid('clientedemo'));
        $this->assertFalse(CpanelUsername::isValid('Clientedemo'));
        $this->assertFalse(CpanelUsername::isValid('1client'));
        $this->assertFalse(CpanelUsername::isValid(str_repeat('a', 17)));
    }
}
