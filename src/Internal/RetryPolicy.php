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
     * Billed lookups retry only a 429 with Retry-After, which the API sends before doing any
     * work, and never reuse a connection, since retrying anything else could charge twice.
     * health() uses it too, so an unhealthy API is reported at once.
     */
    case Billed;
}
