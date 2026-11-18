<?php

declare(strict_types=1);

namespace Synthient\Model;

/**
 * Patterns in a domain's hourly activity, in UTC.
 *
 * @deprecated Domain lookup was removed from the Synthient API and will stop working when the
 *             endpoint is turned off. It will be removed in the next major version.
 */
final readonly class ActivityStats
{
    /**
     * @param string $cadence a sentence describing the pattern, see Cadence
     * @param int $peakHour the busiest hour, 0 to 23
     * @param int $quietHour the quietest hour, 0 to 23
     */
    public function __construct(
        public string $cadence,
        public int $medianPerHour,
        public int $p95PerHour,
        public int $peakHour,
        public int $quietHour,
    ) {}
}
