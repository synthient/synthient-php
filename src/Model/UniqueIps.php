<?php

declare(strict_types=1);

namespace Synthient\Model;

use Synthient\Internal\Wire;

/**
 * Distinct source addresses seen for a domain.
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
