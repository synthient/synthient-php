<?php

declare(strict_types=1);

namespace Synthient\Model;

use DateTimeImmutable;
use Synthient\Internal\Wire;

/**
 * @deprecated Domain lookup was removed from the Synthient API and will stop working when the
 *             endpoint is turned off. It will be removed in the next major version.
 */
final readonly class DomainEvent
{
    /**
     * @param string $sourceIp the proxy exit address that carried the event; despite the JSON name it is not masked
     */
    public function __construct(
        public DateTimeImmutable $timestamp,
        #[Wire(name: 'source_ip_masked')]
        public string $sourceIp,
        public string $targetSubdomain,
        public int $port,
        public string $countryCode,
    ) {}
}
