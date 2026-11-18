<?php

declare(strict_types=1);

namespace Synthient\Model;

use DateTimeImmutable;
use Synthient\Internal\Wire;

final readonly class ExportMeta
{
    /**
     * @param string $kind hourly or daily, see ExportKind
     * @param string $format the format that $size and $checksum describe: the one requested,
     *                       else parquet, or tar.gz for WHOIS. See ExportFormat
     * @param DateTimeImmutable $date the start of the day or hour the export covers
     * @param int $size the file size in bytes
     * @param string $checksum the hex SHA-256 of the file
     * @param ParquetSchema $schema the file's columns, empty for a WHOIS archive
     * @param int|null $hour the hour of an hourly export, or null for a daily one
     * @param list<ExportFile> $formats every file of the snapshot, its own format first
     * @param list<ExportEntity>|null $entities the Parquet files inside a WHOIS archive, each
     *                                          with its columns
     * @param string|null $variant lite when the lite file was requested, see ExportVariant
     */
    public function __construct(
        public string $id,
        public string $stream,
        public string $kind,
        public string $format,
        public DateTimeImmutable $date,
        public int $rows,
        public int $size,
        public string $checksum,
        public DateTimeImmutable $createdAt,
        public ParquetSchema $schema,
        public ?int $hour = null,
        #[Wire(list: ExportFile::class)]
        public array $formats = [],
        #[Wire(list: ExportEntity::class)]
        public ?array $entities = null,
        public ?string $variant = null,
    ) {}
}
