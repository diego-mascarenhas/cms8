<?php

namespace Tests\Unit;

use App\Helpers\PhoneHelper;
use PHPUnit\Framework\TestCase;

class PhoneHelperDigitsBelongToSameLineTest extends TestCase
{
    public function test_empty_returns_false(): void
    {
        $this->assertFalse(PhoneHelper::digitsBelongToSameLine(null, null));
        $this->assertFalse(PhoneHelper::digitsBelongToSameLine('123', null));
    }

    public function test_exact_match(): void
    {
        $this->assertTrue(PhoneHelper::digitsBelongToSameLine('5491167284492', '5491167284492'));
    }

    public function test_suffix_match_when_country_code_differs(): void
    {
        $this->assertTrue(PhoneHelper::digitsBelongToSameLine('5491167284492', '1167284492'));
    }
}
