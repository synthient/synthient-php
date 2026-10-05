<?php

declare(strict_types=1);

namespace Synthient\Event;

use DateTimeImmutable;
use Synthient\Internal\Wire;

/**
 * An HTTP request captured by a Helios honeypot.
 */
final readonly class HeliosHttpEvent
{
    /**
     * @param string $tunnelId a uint64, as a decimal string because it can exceed PHP_INT_MAX
     * @param string $domain the host the tunnel was opened to
     * @param int $port the destination port
     * @param string|null $raw the raw request, base64-encoded
     */
    public function __construct(
        public DateTimeImmutable $timestamp,
        #[Wire(type: 'uint64')]
        public string $tunnelId,
        public string $domain,
        public int $port,
        public string $protocol,
        public HeliosMeta $meta,
        public ?HttpRequestDetails $details = null,
        public ?string $raw = null,
    ) {}
}
