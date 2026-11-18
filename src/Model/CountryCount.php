<?php

declare(strict_types=1);

namespace Synthient\Model;

/**
 * @deprecated Domain lookup was removed from the Synthient API and will stop working when the
 *             endpoint is turned off. It will be removed in the next major version.
 */
final readonly class CountryCount
{
    public function __construct(
        public string $countryCode,
        public int $events,
        public int $uniqueIps,
    ) {}
}
