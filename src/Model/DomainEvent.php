<?php

declare(strict_types=1);

namespace Synthient\Model;

use DateTimeImmutable;
use Synthient\Internal\Wire;

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
