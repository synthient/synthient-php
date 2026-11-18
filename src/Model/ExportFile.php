<?php

declare(strict_types=1);

namespace Synthient\Model;

/**
 * One file of an export, in one format.
 */
final readonly class ExportFile
{
    /**
     * @param string $format see ExportFormat
     * @param string $checksum the hex SHA-256 of the file as downloaded, so of the .csv.gz for csv
     */
    public function __construct(
        public string $format,
        public int $sizeBytes,
        public string $checksum,
    ) {}
}
