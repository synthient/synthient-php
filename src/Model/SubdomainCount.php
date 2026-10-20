<?php

declare(strict_types=1);

namespace Synthient\Model;

final readonly class SubdomainCount
{
    public function __construct(
        public string $subdomain,
        public int $count,
    ) {}
}
