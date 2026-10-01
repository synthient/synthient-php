<?php

declare(strict_types=1);

namespace Synthient\Model;

use DateTimeImmutable;

/**
 * The API key that made a request. It never includes the secret.
 */
final readonly class AccountApiKey
{
    public function __construct(
        public string $id,
        public string $name,
        public DateTimeImmutable $createdAt,
    ) {}
}
