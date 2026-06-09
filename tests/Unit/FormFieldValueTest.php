<?php

namespace Tests\Unit;

use App\Support\FormFieldValue;
use PHPUnit\Framework\TestCase;

class FormFieldValueTest extends TestCase
{
    public function test_normalizes_doubly_encoded_apostrophe(): void
    {
        $this->assertSame(
            "REVISION ALPHA's Team",
            FormFieldValue::normalize('REVISION ALPHA&amp;#039;s Team'),
        );
    }

    public function test_normalizes_single_encoded_apostrophe(): void
    {
        $this->assertSame(
            "REVISION ALPHA's Team",
            FormFieldValue::normalize('REVISION ALPHA&#039;s Team'),
        );
    }

    public function test_leaves_plain_apostrophe_unchanged(): void
    {
        $this->assertSame(
            "REVISION ALPHA's Team",
            FormFieldValue::normalize("REVISION ALPHA's Team"),
        );
    }
}
