<?php

declare(strict_types=1);

namespace Synthient\Model;

final readonly class ExportVolumeDay
{
    /**
     * @param string $date the day as YYYY-MM-DD, in UTC
     * @param string $kind daily when the day's rollup is the total, or hourly when its hourly
     *                     exports were summed because the day is today or its rollup is pending,
     *                     see ExportKind
     * @param int $snapshots the exports counted: 1 for a rollup, else the hourly exports so far
     */
    public function __construct(
        public string $date,
        public string $kind,
        public int $snapshots,
        public int $rowCount,
        public int $sizeBytes,
    ) {}
}
