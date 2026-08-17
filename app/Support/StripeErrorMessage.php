<?php

namespace App\Support;

use Stripe\Exception\ApiErrorException;

/**
 * Formats Stripe API errors for logs and for safe display. Stripe returns
 * structured fields (type, code, message, param); the PHP exception wraps them.
 *
 * @see https://docs.stripe.com/api/errors
 */
class StripeErrorMessage
{
    /**
     * Text suitable to show the user: Stripe's message (often English) plus
     * type/code when present so support can trace the issue.
     */
    public static function display(\Throwable $e): string
    {
        if (! $e instanceof ApiErrorException)
        {
            return $e->getMessage();
        }

        $err = $e->getError();
        $message = $err?->message ?? $e->getMessage();
        $meta = [];
        if ($err?->type)
        {
            $meta[] = 'type: '.$err->type;
        }
        if ($err?->code)
        {
            $meta[] = 'code: '.$err->code;
        }
        if ($err?->param)
        {
            $meta[] = 'param: '.$err->param;
        }
        if ($e->getRequestId())
        {
            $meta[] = 'req_id: '.$e->getRequestId();
        }

        if ($meta === [])
        {
            return $message;
        }

        return $message.' ('.implode(', ', $meta).')';
    }

    /**
     * Structured context for Log::error / Log::warning.
     *
     * @return array<string, int|string|null>
     */
    public static function logContext(\Throwable $e): array
    {
        if (! $e instanceof ApiErrorException)
        {
            return ['message' => $e->getMessage()];
        }

        $err = $e->getError();

        return array_filter([
            'exception' => $e::class,
            'stripe_type' => $err?->type,
            'stripe_code' => $err?->code,
            'stripe_param' => $err?->param,
            'stripe_message' => $err?->message,
            'stripe_doc_url' => $err?->doc_url,
            'request_id' => $e->getRequestId(),
            'http_status' => $e->getHttpStatus(),
        ], fn ($v) => $v !== null && $v !== '');
    }

    public static function isMissingCustomer(\Throwable $e): bool
    {
        if (! $e instanceof ApiErrorException)
        {
            return false;
        }

        $err = $e->getError();
        $code = $err?->code ?? $e->getStripeCode();
        if ($code !== 'resource_missing')
        {
            return false;
        }

        $message = strtolower((string) ($err?->message ?? $e->getMessage()));

        return $err?->param === 'id' || str_contains($message, 'no such customer');
    }
}
