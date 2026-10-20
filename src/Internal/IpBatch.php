<?php

declare(strict_types=1);

namespace Synthient\Internal;

use Synthient\Model\IpInfo;

/**
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
