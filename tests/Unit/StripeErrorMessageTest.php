<?php

namespace Tests\Unit;

use App\Support\StripeErrorMessage;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\InvalidRequestException;

#[CoversClass(StripeErrorMessage::class)]
class StripeErrorMessageTest extends TestCase
{
    public function test_display_falls_back_to_message_for_non_stripe_exception(): void
    {
        $e = new Exception('Generic failure');
        $this->assertSame('Generic failure', StripeErrorMessage::display($e));
    }

    public function test_display_includes_stripe_structured_fields(): void
    {
        $e = InvalidRequestException::factory(
            'Invalid value for eu_vat',
            400,
            null,
            [
                'error' => [
                    'type' => 'invalid_request_error',
                    'code' => 'tax_id_invalid',
                    'message' => 'Invalid value for eu_vat',
                ],
            ],
        );

        $out = StripeErrorMessage::display($e);
        $this->assertStringContainsString('Invalid value for eu_vat', $out);
        $this->assertStringContainsString('invalid_request_error', $out);
    }

    public function test_log_context_for_generic_exception(): void
    {
        $this->assertSame(
            ['message' => 'x'],
            StripeErrorMessage::logContext(new Exception('x')),
        );
    }
}
