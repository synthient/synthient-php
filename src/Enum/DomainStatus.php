<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * Whether a domain had events in the last 24 hours. A domain that was never observed is inactive with zeroed stats.
 */
final class DomainStatus
{
    public const string ACTIVE = 'active';
    public const string INACTIVE = 'inactive';
}
