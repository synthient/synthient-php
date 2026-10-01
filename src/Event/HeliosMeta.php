<?php

declare(strict_types=1);

namespace Synthient\Event;

/**
 * Where a Helios honeypot capture came from.
 */
final readonly class HeliosMeta
{
    /**
     * @param string|null $proxyIp the proxy address the traffic arrived from
     */
    public function __construct(
        public ?string $proxyIp = null,
        public ?string $server = null,
        public ?string $poolId = null,
        public ?string $provider = null,
    ) {}
}
