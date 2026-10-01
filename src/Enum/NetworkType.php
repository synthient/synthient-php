<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * The kind of network an address belongs to. The API may add values.
 */
final class NetworkType
{
    public const string MOBILE = 'MOBILE';
    public const string SATELLITE = 'SATELLITE';
    public const string IN_FLIGHT_WIFI = 'IN_FLIGHT_WIFI';
    public const string RESIDENTIAL = 'RESIDENTIAL';
    public const string CORPORATE = 'CORPORATE';
    public const string ACADEMIC = 'ACADEMIC';
    public const string DATACENTER = 'DATACENTER';
    public const string GOVERNMENT = 'GOVERNMENT';
}
