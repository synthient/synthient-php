<?php

declare(strict_types=1);

namespace Synthient\Model;

use DateTimeImmutable;

final readonly class ExportMeta
{
    /**
     * @param string $kind hourly or daily, see ExportKind
     * @param string $format always parquet
     * @param DateTimeImmutable $date the start of the day or hour the export covers
     * @param int $size the file size in bytes
     * @param string $checksum the hex SHA-256 of the file
     * @param int|null $hour the hour of an hourly export, or null for a daily one
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
    ) {}
}
