<?php

declare(strict_types=1);

namespace Synthient\Event;

use DateTimeImmutable;
use Synthient\Internal\Wire;

/**
 * A TLS handshake captured by a Helios honeypot.
 */
final readonly class HeliosHttpsEvent
{
    /**
     * @param string $tunnelId a uint64, as a decimal string because it can exceed PHP_INT_MAX
     * @param string $domain the host the tunnel was opened to
     * @param int $port the destination port
     * @param TlsClientHello|null $details null when the sensor could not parse the handshake
     * @param string|null $raw always null, because the sensors do not capture handshake bytes
     */
    public function __construct(
        public DateTimeImmutable $timestamp,
        #[Wire(type: 'uint64')]
        public string $tunnelId,
        public string $domain,
        public int $port,
        public string $protocol,
        public HeliosMeta $meta,
        public ?TlsClientHello $details = null,
        public ?string $raw = null,
    ) {}
}
