<?php

declare(strict_types=1);

namespace Synthient\Model;

/**
 * Activity from one country.
 */
final readonly class CountryCount
{
    public function __construct(
        public string $countryCode,
        public int $events,
        public int $uniqueIps,
    ) {}
}
