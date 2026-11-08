<?php

namespace Tests\Unit;

use App\Helpers\PhoneHelper;
use PHPUnit\Framework\TestCase;

class PhoneHelperDisplayTest extends TestCase
{
    public function test_argentine_numbers_keep_the_country_and_area_codes_apart(): void
    {
        $this->assertSame('+54 9 11 6014 1638', PhoneHelper::formatForDisplayReadable('5491160141638'));
        $this->assertSame('+54 11 4371 5915', PhoneHelper::formatForDisplayReadable('541143715915'));
        $this->assertSame('+34 722 372 858', PhoneHelper::formatForDisplayReadable('34722372858'));
    }

    public function test_empty_phone_returns_nothing(): void
    {
        $this->assertNull(PhoneHelper::formatForDisplayReadable(null));
        $this->assertNull(PhoneHelper::formatForDisplayReadable(''));
        $this->assertNull(PhoneHelper::formatForDisplayReadable('   '));
    }
}
