<?php

namespace App\Services\Fiscal;

use App\Models\FiscalExport;

class FiscalExportResult
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     */
    private function __construct(
        public readonly string $status,
        public readonly ?string $externalId = null,
        public readonly ?string $externalNumber = null,
        public readonly ?string $externalCustomerId = null,
        public readonly ?string $errorMessage = null,
        public readonly array $payload = [],
        public readonly array $response = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     */
    public static function exported(
        string $externalId,
        ?string $externalNumber = null,
        ?string $externalCustomerId = null,
        array $payload = [],
        array $response = [],
    ): self {
        return new self(
            status: FiscalExport::STATUS_EXPORTED,
            externalId: $externalId,
            externalNumber: $externalNumber,
            externalCustomerId: $externalCustomerId,
            payload: $payload,
            response: $response,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     */
    public static function rectified(
        string $externalId,
        ?string $externalNumber = null,
        array $payload = [],
        array $response = [],
    ): self {
        return new self(
            status: FiscalExport::STATUS_RECTIFIED,
            externalId: $externalId,
            externalNumber: $externalNumber,
            payload: $payload,
            response: $response,
        );
    }

    public static function skipped(string $reason): self
    {
        return new self(status: FiscalExport::STATUS_SKIPPED, errorMessage: $reason);
    }

    public static function failed(string $reason): self
    {
        return new self(status: FiscalExport::STATUS_FAILED, errorMessage: $reason);
    }

    public function isExported(): bool
    {
        return $this->status === FiscalExport::STATUS_EXPORTED;
    }

    public function isRectified(): bool
    {
        return $this->status === FiscalExport::STATUS_RECTIFIED;
    }

    public function isFailed(): bool
    {
        return $this->status === FiscalExport::STATUS_FAILED;
    }
}
