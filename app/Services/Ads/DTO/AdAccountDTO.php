<?php

namespace App\Services\Ads\DTO;

class AdAccountDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $currency = null,
        public readonly ?string $status = null,
    ) {}

    /**
     * @return array{id: string, name: string, currency: ?string, status: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'currency' => $this->currency,
            'status' => $this->status,
        ];
    }
}
