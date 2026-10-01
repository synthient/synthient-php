<?php

declare(strict_types=1);

namespace Synthient\Event;

use DateTimeImmutable;

/**
 * An anonymizer IP range from the anonymizers feed. Every field is null when the API omits it.
 */
final readonly class AnonymizerEvent
{
    /**
     * @param string|null $type see ProxyType
     * @param string|null $countryCode the country of rangeStart
     * @param int|null $asn the autonomous system of rangeStart
     */
    public function __construct(
        public ?string $rangeStart = null,
        public ?string $rangeEnd = null,
        public ?string $provider = null,
        public ?string $type = null,
        public ?string $countryCode = null,
        public ?int $asn = null,
        public ?DateTimeImmutable $timestamp = null,
    ) {}
}
