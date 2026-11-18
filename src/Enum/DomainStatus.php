<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * Whether a domain had events in the last 24 hours.
 *
 * @deprecated Domain lookup was removed from the Synthient API and will stop working when the
 *             endpoint is turned off. It will be removed in the next major version.
 */
final class DomainStatus
{
    public const string ACTIVE = 'active';
    public const string INACTIVE = 'inactive';
}
