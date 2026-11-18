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
 * A Synthient data feed, with a real-time stream and daily and hourly Parquet exports.
 *
 * Streaming needs the feed's _STREAM scope (PROXY_FIREHOSE for proxies), and exports need
 * its _FEED scope (PROXY_FEEDS for proxies).
 *
 * An export is named by an ID: "latest" for the newest hourly export, "YYYY-MM-DD" for a
 * daily one, or "YYYY-MM-DD/H" for an hourly one. A DateTimeInterface names the daily
 * export of its UTC date. Hourly IDs can be built with $time->format('Y-m-d/G') in UTC.
 * An hourly export can be fetched until its day's rollup, shortly after 00:30 UTC the next
 * day; a daily export still rolling up answers 404 with a retryAfter.
 *
 * Proxies, anonymizers, and ja4t also publish a deduplicated lite file of each export. Pass
 * ExportVariant::LITE as variant to list or fetch it, which answers 404 until it is published.
 * A lite listing's IDs name the full files, so pass the variant again to fetch one. The other
 * feeds have no lite files.
 *
 * @template T of object
 */
final readonly class Feed
{
    private Exports $exports;

    /**
     * @internal
     *
     * @param class-string<T> $event
     */
    public function __construct(
        private Transport $transport,
        private string $path,
        private string $event,
    ) {
        $this->exports = new Exports($transport, $path, true);
    }

    /**
     * Connects at once, so errors such as a missing scope (403) or too many open streams (429)
     * are thrown here, before iteration.
     *
     * @return EventStream<T>
     */
    public function stream(): EventStream
    {
        return new EventStream($this->transport->openStream("{$this->path}/stream"), $this->event);
    }

    /**
     * Lists one page of exports, newest first.
     *
     * @param int|null $limit the page size, at least 1; the API defaults to 100 and clamps
     *                        values above 500
     * @param string|null $cursor the nextCursor of the previous page
     * @param string|DateTimeInterface|null $day only that UTC day's exports, as YYYY-MM-DD or
     *                                           the day of a DateTimeInterface
     * @param string|null $variant lite to list the lite files, see ExportVariant
     */
    public function listExports(
        ?int $limit = null,
        ?string $cursor = null,
        string|DateTimeInterface|null $day = null,
        ?string $variant = null,
    ): ExportPage {
        return $this->exports->page($limit, $cursor, ['day' => $day, 'variant' => $variant]);
    }

    /**
     * Iterates over every export, newest first, fetching pages as needed. The first request
     * happens on the first iteration, and breaking out fetches nothing more.
     *
     * @return Generator<int, Export>
     */
    public function allExports(
        ?int $limit = null,
        ?string $cursor = null,
        string|DateTimeInterface|null $day = null,
        ?string $variant = null,
    ): Generator {
        return $this->exports->all($limit, $cursor, ['day' => $day, 'variant' => $variant]);
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
     * @param string|null $variant lite for the lite file, see ExportVariant
     */
    public function exportMeta(string|DateTimeInterface $export, ?string $variant = null): ExportMeta
    {
        return $this->exports->meta($export, ['variant' => $variant]);
    }

    /**
     * Returns the presigned URL of an export's file. It is valid for 24 hours and grants
     * access without an API key, so treat it as a secret.
     *
     * @param string|null $variant lite for the lite file, see ExportVariant
     */
    public function downloadUrl(string|DateTimeInterface $export, ?string $variant = null): string
    {
        return $this->exports->downloadUrl($export, ['variant' => $variant]);
    }

    /**
     * Opens an export's Parquet file for reading. The caller must close the stream. Compare
     * the file's SHA-256 with the export's checksum to verify it.
     *
     * @param string|null $variant lite for the lite file, see ExportVariant
     */
    public function download(string|DateTimeInterface $export, ?string $variant = null): StreamInterface
    {
        return $this->exports->download($export, ['variant' => $variant]);
    }
}
