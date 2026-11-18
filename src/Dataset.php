<?php

declare(strict_types=1);

namespace Synthient;

use DateTimeInterface;
use Generator;
use Psr\Http\Message\StreamInterface;
use Synthient\Internal\Exports;
use Synthient\Internal\Transport;
use Synthient\Model\Export;
use Synthient\Model\ExportMeta;
use Synthient\Model\ExportPage;
use Synthient\Model\ExportVolume;

/**
 * A Synthient dataset, published as one export a day, with no stream and no hourly exports.
 *
 * Exports need the dataset's _FEED scope: GEOLOCATION_FEED or WHOIS_FEED.
 *
 * An export is named by an ID: "latest" for the newest day, or "YYYY-MM-DD" for a given one.
 * A DateTimeInterface names the export of its UTC date. Hourly IDs throw
 * InvalidArgumentException.
 *
 * Each export has files in several formats, see ExportFormat: geolocation has parquet, the
 * default, csv, and mmdb, and WHOIS has only tar.gz.
 */
final readonly class Dataset
{
    private Exports $exports;

    /**
     * @internal
     */
    public function __construct(Transport $transport, string $path)
    {
        $this->exports = new Exports($transport, $path, false);
    }

    /**
     * Lists one page of exports, newest first.
     *
     * @param int|null $limit the page size, at least 1; the API defaults to 100 and clamps
     *                        values above 500
     * @param string|null $cursor the nextCursor of the previous page
     * @param string|DateTimeInterface|null $day only that UTC day's export, as YYYY-MM-DD or
     *                                           the day of a DateTimeInterface
     */
    public function listExports(?int $limit = null, ?string $cursor = null, string|DateTimeInterface|null $day = null): ExportPage
    {
        return $this->exports->page($limit, $cursor, ['day' => $day]);
    }

    /**
     * Iterates over every export, newest first, fetching pages as needed. The first request
     * happens on the first iteration, and breaking out fetches nothing more.
     *
     * @return Generator<int, Export>
     */
    public function allExports(?int $limit = null, ?string $cursor = null, string|DateTimeInterface|null $day = null): Generator
    {
        return $this->exports->all($limit, $cursor, ['day' => $day]);
    }

    /**
     * Returns the rows and bytes exported per UTC day, oldest first.
     *
     * @param int|null $days how many days back, today included; the API defaults to 90 and
     *                       accepts 1 to 366
     */
    public function exportVolume(?int $days = null): ExportVolume
    {
        return $this->exports->volume($days);
    }

    /**
     * @param string|null $format the file whose size and checksum to return, see ExportFormat
     */
    public function exportMeta(string|DateTimeInterface $export, ?string $format = null): ExportMeta
    {
        return $this->exports->meta($export, ['format' => $format]);
    }

    /**
     * Returns the presigned URL of an export's file. It is valid for 24 hours and grants
     * access without an API key, so treat it as a secret.
     *
     * @param string|null $format see ExportFormat; the API answers 404 when the export has no
     *                            file in that format
     */
    public function downloadUrl(string|DateTimeInterface $export, ?string $format = null): string
    {
        return $this->exports->downloadUrl($export, ['format' => $format]);
    }

    /**
     * Opens an export's file for reading, exactly as served, so a csv stays gzip-compressed
     * and a WHOIS archive stays a tar.gz. The caller must close the stream. Compare the
     * file's SHA-256 with the checksum of its format to verify it.
     *
     * @param string|null $format see ExportFormat; the API answers 404 when the export has no
     *                            file in that format
     */
    public function download(string|DateTimeInterface $export, ?string $format = null): StreamInterface
    {
        return $this->exports->download($export, ['format' => $format]);
    }
}
