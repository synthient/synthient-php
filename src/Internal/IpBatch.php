<?php

declare(strict_types=1);

namespace Synthient\Internal;

use Synthient\Model\IpInfo;

/**
 * The response of a batch IP lookup.
 *
 * @internal
 */
final readonly class IpBatch
{
    /**
     * @param list<IpInfo> $results
     */
    public function __construct(
        #[Wire(list: IpInfo::class)]
        public array $results,
    ) {}
}
