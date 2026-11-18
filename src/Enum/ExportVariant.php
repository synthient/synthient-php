<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * Which file of a proxies, anonymizers, or JA4T snapshot to fetch. FULL, the default, holds
 * every sighting. LITE holds the latest row per address and provider, or per range and
 * provider for anonymizers, and is published shortly after its snapshot.
 */
final class ExportVariant
{
    public const string FULL = 'full';
    public const string LITE = 'lite';
}
