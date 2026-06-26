<?php

namespace Tests\Unit;

use App\Rules\DomainWithExtension;
use PHPUnit\Framework\TestCase;

class DomainWithExtensionTest extends TestCase
{
    public function test_accepts_domain_with_extension(): void
    {
        $rule = new DomainWithExtension;
        $failed = false;

        $rule->validate('domain', 'pepe5.com', function () use (&$failed): void
        {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_rejects_domain_without_extension(): void
    {
        $rule = new DomainWithExtension;
        $message = null;

        $rule->validate('domain', 'pepe5', function (string $msg) use (&$message): void
        {
            $message = $msg;
        });

        $this->assertSame('El dominio no es válido. Debe incluir una extensión (ejemplo: ejemplo.com).', $message);
    }
}
