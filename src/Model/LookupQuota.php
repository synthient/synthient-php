<?php

declare(strict_types=1);

namespace Synthient\Model;

/**
 * The team's remaining lookup credits.
 */
final readonly class LookupQuota
{
    /**
     * @param int $resetsIn seconds until the credits reset
     */
    public function __construct(
        public int $credits,
        public int $resetsIn,
    ) {}
}
