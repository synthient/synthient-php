<?php

declare(strict_types=1);

namespace Synthient\Model;

final readonly class AsnCount
{
    public function __construct(
        public int $asn,
        public int $events,
    ) {}
}
