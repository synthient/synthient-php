<?php

declare(strict_types=1);

namespace Synthient\Model;

use DateTimeImmutable;

final readonly class Device
{
    /**
     * @param string $os see OperatingSystem
     */
    public function __construct(
        public string $os,
        public string $version,
        public DateTimeImmutable $lastSeen,
    ) {}
}
