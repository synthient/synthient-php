<?php

declare(strict_types=1);

namespace Synthient\Event;

/**
 * A file in a torrent.
 */
final readonly class TorrentFile
{
    public function __construct(
        public ?string $path = null,
        public ?int $length = null,
    ) {}
}
