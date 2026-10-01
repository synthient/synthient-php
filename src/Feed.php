<?php

declare(strict_types=1);

namespace Synthient;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Synthient\Exception\SynthientException;
use Synthient\Internal\RetryPolicy;
use Synthient\Internal\Transport;
use Synthient\Model\Export;
use Synthient\Model\ExportMeta;
use Synthient\Model\ExportPage;

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
 * @template T of object
 */
final readonly class Feed
{
    /**
     * @internal
     *
     * @param class-string<T> $event
     */
    public function __construct(
        private Transport $transport,
        private string $path,
        private string $event,
    ) {}

    /**
     * Connects to the feed's real-time stream. Errors such as a missing scope (403) or too
     * many open streams (429) are thrown here, before iteration.
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
     * @param int|null $limit the page size, from 1 to 500; the API defaults to 100
     * @param string|null $cursor the nextCursor of the previous page
     */
    public function listExports(?int $limit = null, ?string $cursor = null): ExportPage
    {
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException('limit must be positive');
        }
        $query = [];
        if ($cursor !== null && $cursor !== '') {
            $query['cursor'] = $cursor;
        }
        if ($limit !== null) {
            $query['limit'] = $limit;
        }

        return $this->transport->request(ExportPage::class, 'GET', "{$this->path}/export", RetryPolicy::Transient, $query);
    }

    /**
     * Iterates over every export, newest first, fetching pages as needed. The first request
     * happens on the first iteration, and breaking out fetches nothing more.
     *
     * @return Generator<int, Export>
     */
    public function allExports(?int $limit = null, ?string $cursor = null): Generator
    {
        $seen = $cursor === null ? [] : [$cursor => true];
        while (true) {
            $page = $this->listExports($limit, $cursor);
            foreach ($page->exports as $export) {
                yield $export;
            }
            $cursor = $page->nextCursor;
            if ($cursor === null || $cursor === '') {
                return;
            }
            if (isset($seen[$cursor])) {
                throw new SynthientException('synthient: export listing returned a repeated cursor');
            }
            $seen[$cursor] = true;
        }
    }

    public function exportMeta(string|DateTimeInterface $export): ExportMeta
    {
        $path = "{$this->path}/export/" . self::exportId($export) . '/meta';

        return $this->transport->request(ExportMeta::class, 'GET', $path, RetryPolicy::Transient);
    }

    /**
     * Returns the presigned URL of an export's file. It is valid for 24 hours and grants
     * access without an API key, so treat it as a secret.
     */
    public function downloadUrl(string|DateTimeInterface $export): string
    {
        return $this->transport->downloadUrl("{$this->path}/export/" . self::exportId($export));
    }

    /**
     * Opens an export's Parquet file for reading. The caller must close the stream. Compare
     * the file's SHA-256 with the export's checksum to verify it.
     */
    public function download(string|DateTimeInterface $export): StreamInterface
    {
        return $this->transport->openDownload($this->downloadUrl($export));
    }

    private static function exportId(string|DateTimeInterface $export): string
    {
        if ($export instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($export)
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d');
        }
        if ($export === 'latest') {
            return $export;
        }
        if (preg_match('#^(\d{4}-\d{2}-\d{2})(?:/(\d{1,2}))?$#', $export, $match) !== 1) {
            throw new InvalidArgumentException('invalid export ID');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $match[1]);
        if ($date === false || $date->format('Y-m-d') !== $match[1]) {
            throw new InvalidArgumentException('invalid export ID');
        }
        if (!isset($match[2])) {
            return $match[1];
        }
        $hour = (int) $match[2];
        if ($hour > 23) {
            throw new InvalidArgumentException('invalid export ID');
        }

        return "{$match[1]}/{$hour}";
    }
}
