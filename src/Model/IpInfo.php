<?php

declare(strict_types=1);

namespace Synthient\Model;

/**
 * A section is null when the API has no data for it.
 */
final readonly class IpInfo
{
    /**
     * @param string $ip the address in canonical form
     */
    public function __construct(
        public string $ip,
        public ?Network $network = null,
        public ?Location $location = null,
        public ?Intelligence $intelligence = null,
    ) {}
}
