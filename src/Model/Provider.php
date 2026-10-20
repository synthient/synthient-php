<?php

declare(strict_types=1);

namespace Synthient\Model;

use DateTimeImmutable;
use Synthient\Internal\Wire;

final readonly class Provider
{
    public function __construct(
        #[Wire(name: 'provider')]
        public string $name,
        public string $type,
        public DateTimeImmutable $lastSeen,
    ) {}
}
