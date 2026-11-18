<?php

declare(strict_types=1);

namespace Synthient\Model;

use DateTimeImmutable;
use Synthient\Internal\Wire;

/**
 * A snapshot of a feed or dataset.
 */
final readonly class Export
{
    /**
     * @param string $id pass it to exportMeta(), downloadUrl(), or download(); latest on the first page means the newest hourly export
     * @param string $kind hourly or daily, see ExportKind
     * @param string $date the snapshot date as YYYY-MM-DD, in UTC
     * @param string $checksum the hex SHA-256 of the file, the first of $formats
     * @param int|null $hour the hour of an hourly export, or null for a daily one
     * @param list<ExportFile> $formats every file of the snapshot, its own format first
     * @param list<ExportEntity>|null $entities the Parquet files inside a WHOIS archive, whose
     *                                          row counts add up to $rowCount
     * @param string|null $variant lite on a lite listing, whose sizes, checksum, and formats
     *                             then describe the lite file; its $id names the full file, so
     *                             pass the variant again to fetch it. See ExportVariant
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
        #[Wire(list: ExportFile::class)]
        public array $formats = [],
        #[Wire(list: ExportEntity::class)]
        public ?array $entities = null,
        public ?string $variant = null,
    ) {}
}
