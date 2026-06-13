<?php

namespace App\Services\Fiscal\Exceptions;

use RuntimeException;
use Throwable;

class FiscalExportException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function validation(string $message): self
    {
        return new self($message, retryable: false);
    }

    public static function rateLimited(string $message, ?int $retryAfterSeconds = null): self
    {
        return new self($message, retryable: true, retryAfterSeconds: $retryAfterSeconds);
    }

    public static function transient(string $message, ?Throwable $previous = null): self
    {
        return new self($message, retryable: true, previous: $previous);
    }
}
