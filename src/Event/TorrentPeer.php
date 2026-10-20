<?php

declare(strict_types=1);

namespace Synthient\Event;

final readonly class TorrentPeer
{
    /**
     * @param string|null $source where the peer was found: DHT, PEX, or tracker
     */
    public function __construct(
        public ?string $ip = null,
        public ?int $port = null,
        public ?string $source = null,
        public ?bool $encrypted = null,
    ) {}
}
