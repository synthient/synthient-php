<?php

declare(strict_types=1);

namespace Synthient;

/**
 * The rate limit state from the RateLimit-* response headers. Limits apply per team and are
 * shared by all of its keys.
 */
final readonly class RateLimit
{
    /**
     * @param int $limit the burst size of the operation's bucket
     * @param int $remaining requests left in the bucket
     * @param int $reset seconds until the bucket is full again
     */
    public function __construct(
        public int $limit,
        public int $remaining,
        public int $reset,
    ) {}
}
