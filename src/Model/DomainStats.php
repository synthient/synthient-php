<?php

declare(strict_types=1);

namespace Synthient\Model;

use Synthient\Internal\Wire;

final readonly class DomainStats
{
    public function __construct(
        #[Wire(name: 'events_24h')]
        public int $events24h,
        #[Wire(name: 'total_events_30d')]
        public int $totalEvents30d,
    ) {}
}
