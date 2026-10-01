<?php

declare(strict_types=1);

namespace Synthient\Model;

/**
 * Events on one port.
 */
final readonly class PortCount
{
    public function __construct(
        public int $port,
        public int $count,
    ) {}
}
