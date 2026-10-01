<?php

declare(strict_types=1);

namespace Synthient\Internal;

/**
 * @internal
 */
enum RetryPolicy
{
    /**
     * Free requests retry 429 with Retry-After, 500, 502, 503, 504, and transport failures.
     */
    case Transient;

    /**
     * Billed lookups retry only a 429 with Retry-After, which the API returns before doing
     * any work. A retried server error or dropped connection could be charged twice.
     */
    case RateLimit;
}
