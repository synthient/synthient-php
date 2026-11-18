<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * The Parquet files inside a WHOIS archive.
 */
final class WhoisEntity
{
    public const string ASNS = 'asns';
    public const string NETWORKS = 'networks';
    public const string ORGANISATIONS = 'organisations';
    public const string CONTACTS = 'contacts';
    public const string MAINTAINERS = 'maintainers';
    public const string TRANSFERS = 'transfers';
    public const string DELEGATIONS = 'delegations';
}
