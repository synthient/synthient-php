<?php

declare(strict_types=1);

namespace Synthient\Model;

/**
 * A Parquet file inside a WHOIS archive.
 */
final readonly class ExportEntity
{
    /**
     * @param string $entity see WhoisEntity
     * @param string $checksum the hex SHA-256 of the Parquet file
     * @param ParquetSchema|null $schema the file's columns, sent by exportMeta() only
     */
    public function __construct(
        public string $entity,
        public int $rowCount,
        public int $sizeBytes,
        public string $checksum,
        public ?ParquetSchema $schema = null,
    ) {}
}
