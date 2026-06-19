<?php

namespace Tests\Unit;

use App\Support\PerformanceDigestReplyParser;
use PHPUnit\Framework\TestCase;

class PerformanceDigestReplyParserTest extends TestCase
{
    public function test_parses_spanish_email_suggestion_wrapper(): void
    {
        $parsed = PerformanceDigestReplyParser::parseEmailSuggestion(
            "Asunto: Re: Presupuesto\n\nHola Ana, te confirmo en breve.\n\nSaludos,",
        );

        $this->assertSame('Re: Presupuesto', $parsed['subject']);
        $this->assertSame('Hola Ana, te confirmo en breve.', $parsed['body']);
    }
}
