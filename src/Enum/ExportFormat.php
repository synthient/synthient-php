<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * The file formats of an export. Feeds have only parquet, geolocation adds csv
 * (gzip-compressed) and mmdb, and WHOIS has only tar.gz. The API may add values.
 */
final class ExportFormat
{
    public const string PARQUET = 'parquet';
    public const string CSV = 'csv';
    public const string MMDB = 'mmdb';
    public const string TAR_GZ = 'tar.gz';
}
