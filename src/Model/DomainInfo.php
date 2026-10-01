<?php

declare(strict_types=1);

namespace Synthient\Model;

use Synthient\Internal\Wire;

/**
 * The result of a domain lookup, covering the last 30 days. A domain that was never observed is inactive with zeroed stats.
 */
final readonly class DomainInfo
{
    /**
     * @param string $status active when the domain had events in the last 24 hours, see DomainStatus
     * @param list<TimeSeriesPoint> $timeSeries daily counts
     * @param list<SubdomainCount> $topSubdomains
     * @param list<PortCount> $topPorts
     * @param list<CountryCount> $geoDistribution
     * @param list<list<int>> $hourDowHeatmap event counts as 7 rows of 24 hours, Monday first, in UTC
     * @param list<DomainEvent> $recentEvents
     * @param AsnCount|null $topAsn null when the domain has no events
     */
    public function __construct(
        public string $domain,
        public string $status,
        public DomainStats $stats,
        public UniqueIps $uniqueIps,
        #[Wire(list: TimeSeriesPoint::class)]
        public array $timeSeries,
        #[Wire(list: SubdomainCount::class)]
        public array $topSubdomains,
        #[Wire(list: PortCount::class)]
        public array $topPorts,
        #[Wire(list: CountryCount::class)]
        public array $geoDistribution,
        #[Wire(list: 'list<int>')]
        public array $hourDowHeatmap,
        public ActivityStats $activityStats,
        #[Wire(list: DomainEvent::class)]
        public array $recentEvents,
        public ?AsnCount $topAsn = null,
    ) {}
}
