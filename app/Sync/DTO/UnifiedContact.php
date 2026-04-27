<?php

namespace App\Sync\DTO;

final readonly class UnifiedContact
{
    public function __construct(
        public string $externalId,
        public ?string $email,
        public ?string $phone,
        public string $givenName,
        public ?string $familyName,
        public bool $deleted,
        public array $metadata = [],
    ) {
    }
}
