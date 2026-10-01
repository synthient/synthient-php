<?php

declare(strict_types=1);

namespace Synthient\Event;

use DateTimeImmutable;

/**
 * An HTTP request captured by a Helios honeypot.
 */
final readonly class HeliosHttpEvent
{
    /**
     * @param string $domain the host the tunnel was opened to
     * @param int $port the destination port
     * @param string|null $raw the raw request, base64-encoded
     */
    public function __construct(
        public DateTimeImmutable $timestamp,
        public int $tunnelId,
        public string $domain,
        public int $port,
        public string $protocol,
        public HeliosMeta $meta,
        public ?HttpRequestDetails $details = null,
        public ?string $raw = null,
    ) {}
}
