<?php

namespace App\Sync\DTO;

use Carbon\CarbonImmutable;

final readonly class UnifiedEvent
{
    public function __construct(
        public string $externalId,
        public string $title,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public bool $allDay,
        public ?string $description,
        public ?string $location,
        public bool $deleted,
        public array $metadata = [],
    ) {
    }
}
