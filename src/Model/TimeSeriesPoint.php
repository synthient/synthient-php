<?php

declare(strict_types=1);

namespace Synthient\Model;

use DateTimeImmutable;

/**
 * One day of a domain's activity.
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
