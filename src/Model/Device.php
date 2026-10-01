<?php

declare(strict_types=1);

namespace Synthient\Model;

use DateTimeImmutable;

/**
 * A device seen behind an address.
 */
final readonly class Device
{
    /**
     * @param string $os the operating system, see OperatingSystem
     */
    public function __construct(
        public string $os,
        public string $version,
        public DateTimeImmutable $lastSeen,
    ) {}
}
