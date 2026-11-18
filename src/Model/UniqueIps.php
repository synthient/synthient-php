<?php

declare(strict_types=1);

namespace Synthient\Model;

use Synthient\Internal\Wire;

/**
 * @deprecated Domain lookup was removed from the Synthient API and will stop working when the
 *             endpoint is turned off. It will be removed in the next major version.
 */
final readonly class UniqueIps
{
    /**
     * @param list<int> $sparkline24h hourly counts over the last 24 hours
     */
    public function __construct(
        #[Wire(name: 'value_24h')]
        public int $value24h,
        #[Wire(name: 'value_30d')]
        public int $value30d,
        #[Wire(name: 'sparkline_24h', list: 'int')]
        public array $sparkline24h,
    ) {}
}
