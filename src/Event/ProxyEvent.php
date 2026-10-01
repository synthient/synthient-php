<?php

declare(strict_types=1);

namespace Synthient\Event;

use DateTimeImmutable;

/**
 * A proxy sighting from the proxies feed. Every field is null when the API omits it.
 */
final readonly class ProxyEvent
{
    /**
     * @param string|null $type see ProxyType
     */
    public function __construct(
        public ?string $ip = null,
        public ?string $provider = null,
        public ?string $type = null,
        public ?string $countryCode = null,
        public ?int $asn = null,
        public ?DateTimeImmutable $timestamp = null,
    ) {}
}
