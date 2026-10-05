<?php

declare(strict_types=1);

namespace Synthient\Event;

use DateTimeImmutable;
use Synthient\Internal\Wire;

/**
 * A tunnel destination captured by a Helios honeypot. Despite the feed name these are destinations, not DNS queries.
 */
final readonly class HeliosDnsEvent
{
    /**
     * @param string $tunnelId a uint64, as a decimal string because it can exceed PHP_INT_MAX
     * @param string $domain the host the tunnel was opened to
     * @param int $port the destination port
     */
    public function __construct(
        public DateTimeImmutable $timestamp,
        #[Wire(type: 'uint64')]
        public string $tunnelId,
        public string $domain,
        public int $port,
        public string $protocol,
        public HeliosMeta $meta,
    ) {}
}
