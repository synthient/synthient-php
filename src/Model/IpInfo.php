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
     * @param Network|null $network null when the address lies in no known network range or the
     *                              enrichment lookup failed
     * @param Location|null $location null together with $network
     */
    public function __construct(
        public string $ip,
        public ?Network $network = null,
        public ?Location $location = null,
        public ?Intelligence $intelligence = null,
    ) {}
}
