<?php

declare(strict_types=1);

namespace Synthient\Model;

use DateTimeImmutable;

/**
 * The key's secret is never included.
 */
final readonly class AccountApiKey
{
    public function __construct(
        public string $id,
        public string $name,
        public DateTimeImmutable $createdAt,
    ) {}
}
