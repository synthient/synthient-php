<?php

declare(strict_types=1);

namespace Synthient\Event;

use DateTimeImmutable;

/**
 * A JA4T TCP fingerprint sighting from the ja4t feed. Every field is null when the API omits it.
 */
final readonly class Ja4tEvent
{
    /**
     * @param string|null $type see ProxyType
     */
    public function __construct(
        public ?string $ip = null,
        public ?string $ja4t = null,
        public ?string $provider = null,
        public ?string $type = null,
        public ?string $countryCode = null,
        public ?int $asn = null,
        public ?DateTimeImmutable $timestamp = null,
    ) {}
}
