<?php

declare(strict_types=1);

namespace Synthient\Model;

/**
 * Events for one subdomain.
 */
final readonly class SubdomainCount
{
    public function __construct(
        public string $subdomain,
        public int $count,
    ) {}
}
