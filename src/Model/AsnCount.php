<?php

declare(strict_types=1);

namespace Synthient\Model;

/**
 * @deprecated Domain lookup was removed from the Synthient API and will stop working when the
 *             endpoint is turned off. It will be removed in the next major version.
 */
final readonly class AsnCount
{
    public function __construct(
        public int $asn,
        public int $events,
    ) {}
}
