<?php

namespace Tests\Unit\Support;

use App\Support\SpamRegistrationName;
use PHPUnit\Framework\TestCase;

class SpamRegistrationNameTest extends TestCase
{
    public function test_real_names_are_kept(): void
    {
        $this->assertFalse(SpamRegistrationName::isRandomToken('Ana Pérez'));
        $this->assertFalse(SpamRegistrationName::isRandomToken('Diego'));
        $this->assertFalse(SpamRegistrationName::isRandomToken('XMLHttpRequest'));
    }

    public function test_generated_tokens_are_rejected(): void
    {
        $this->assertTrue(SpamRegistrationName::isRandomToken('PpkRIqEwHZHKvLfEgMUKzOk'));
        $this->assertTrue(SpamRegistrationName::isRandomToken('KjKzHFarqBPOZXVDABz'));
        $this->assertTrue(SpamRegistrationName::isRandomToken('kYkJdheskOuwAxOOBxSI'));
    }
}
