<?php

declare(strict_types=1);

namespace Synthient\Event;

final readonly class TorrentFile
{
    public function __construct(
        public ?string $path = null,
        public ?int $length = null,
    ) {}
}
