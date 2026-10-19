<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * Whether a domain had events in the last 24 hours.
 */
final class DomainStatus
{
    public const string ACTIVE = 'active';
    public const string INACTIVE = 'inactive';
}
