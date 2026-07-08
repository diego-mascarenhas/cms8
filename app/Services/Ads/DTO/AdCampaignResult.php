<?php

namespace App\Services\Ads\DTO;

class AdCampaignResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalCampaignId = null,
        public readonly ?string $error = null,
        public readonly array $payload = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function ok(string $externalCampaignId, array $payload = []): self
    {
        return new self(true, $externalCampaignId, null, $payload);
    }

    public static function fail(string $error): self
    {
        return new self(false, null, $error);
    }
}
