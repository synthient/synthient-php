<?php

declare(strict_types=1);

namespace Synthient\Model;

final readonly class Location
{
    /**
     * @param string $country the ISO 3166-1 alpha-2 country code
     * @param string $timezone the IANA time zone
     */
    public function __construct(
        public string $country,
        public string $state,
        public string $city,
        public string $timezone,
        public float $latitude,
        public float $longitude,
        public string $geoHash,
    ) {}
}
