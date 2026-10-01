<?php

declare(strict_types=1);

namespace Synthient\Model;

use DateTimeImmutable;

/**
 * A Parquet snapshot of a feed.
 */
final readonly class Export
{
    /**
     * @param string $id pass it to exportMeta(), downloadUrl(), or download(); latest on the first page means the newest hourly export
     * @param string $kind hourly or daily, see ExportKind
     * @param string $date the snapshot date as YYYY-MM-DD, in UTC
     * @param string $checksum the hex SHA-256 of the file
     * @param int|null $hour the hour of an hourly export, or null for a daily one
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $date,
        public int $sizeBytes,
        public int $rowCount,
        public string $checksum,
        public DateTimeImmutable $createdAt,
        public string $downloadPath,
        public ?int $hour = null,
    ) {}
}
