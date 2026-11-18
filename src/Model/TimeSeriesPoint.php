<?php

declare(strict_types=1);

namespace Synthient\Model;

use DateTimeImmutable;

/**
 * @deprecated Domain lookup was removed from the Synthient API and will stop working when the
 *             endpoint is turned off. It will be removed in the next major version.
 */
final readonly class TimeSeriesPoint
{
    /**
     * @param DateTimeImmutable $date the start of the day, in UTC
     */
    public function __construct(
        public DateTimeImmutable $date,
        public int $events,
        public int $uniqueIps,
    ) {}
}
