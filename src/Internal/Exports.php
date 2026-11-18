<?php

declare(strict_types=1);

namespace Synthient\Internal;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Synthient\Exception\SynthientException;
use Synthient\Model\Export;
use Synthient\Model\ExportMeta;
use Synthient\Model\ExportPage;
use Synthient\Model\ExportVolume;

/**
 * The export routes that feeds and datasets share. Query parameters that are null are left
 * out, and a DateTimeInterface is sent as its UTC date.
 *
 * @internal
 */
final readonly class Exports
{
    public function __construct(
        private Transport $transport,
        private string $path,
        private bool $hourly,
    ) {}

    /**
     * @param array<string, string|DateTimeInterface|null> $filters
     */
    public function page(?int $limit, ?string $cursor, array $filters): ExportPage
    {
        self::checkLimit($limit);
        $query = [];
        if ($cursor !== null && $cursor !== '') {
            $query['cursor'] = $cursor;
        }
        if ($limit !== null) {
            $query['limit'] = $limit;
        }

        return $this->transport->request(ExportPage::class, 'GET', "{$this->path}/export", RetryPolicy::Transient, $query + $this->query($filters));
    }

    /**
     * @param array<string, string|DateTimeInterface|null> $filters sent with every page
     *
     * @return Generator<int, Export>
     */
    public function all(?int $limit, ?string $cursor, array $filters): Generator
    {
        self::checkLimit($limit);

        return $this->pages($limit, $cursor, $filters);
    }

    public function volume(?int $days): ExportVolume
    {
        return $this->transport->request(ExportVolume::class, 'GET', "{$this->path}/export/daily", RetryPolicy::Transient, $days === null ? [] : ['days' => $days]);
    }

    /**
     * @param array<string, string|null> $params
     */
    public function meta(string|DateTimeInterface $export, array $params): ExportMeta
    {
        $path = "{$this->path}/export/{$this->exportId($export)}/meta";

        return $this->transport->request(ExportMeta::class, 'GET', $path, RetryPolicy::Transient, $this->query($params));
    }

    /**
     * @param array<string, string|null> $params
     */
    public function downloadUrl(string|DateTimeInterface $export, array $params): string
    {
        return $this->transport->downloadUrl("{$this->path}/export/{$this->exportId($export)}", $this->query($params));
    }

    /**
     * @param array<string, string|null> $params
     */
    public function download(string|DateTimeInterface $export, array $params): StreamInterface
    {
        return $this->transport->openDownload("{$this->path}/export/{$this->exportId($export)}", $this->query($params));
    }

    /**
     * @param array<string, string|DateTimeInterface|null> $filters
     *
     * @return Generator<int, Export>
     */
    private function pages(?int $limit, ?string $cursor, array $filters): Generator
    {
        $seen = $cursor === null ? [] : [$cursor => true];
        while (true) {
            $page = $this->page($limit, $cursor, $filters);
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

    /**
     * @param array<string, string|DateTimeInterface|null> $params
     *
     * @return array<string, string>
     */
    private function query(array $params): array
    {
        $query = [];
        foreach ($params as $name => $value) {
            if ($value !== null) {
                $query[$name] = $value instanceof DateTimeInterface ? $this->exportId($value) : $value;
            }
        }

        return $query;
    }

    private function exportId(string|DateTimeInterface $export): string
    {
        if ($export instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($export)
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d');
        }
        if ($export === 'latest') {
            return $export;
        }
        if (
            preg_match('#^(\d{4})-(\d{2})-(\d{2})(?:/(\d{1,2}))?$#', $export, $match) !== 1
            || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])
            || (int) ($match[4] ?? 0) > 23
            || (isset($match[4]) && !$this->hourly)
        ) {
            throw new InvalidArgumentException('invalid export ID');
        }
        $date = "{$match[1]}-{$match[2]}-{$match[3]}";

        return isset($match[4]) ? $date . '/' . (int) $match[4] : $date;
    }

    private static function checkLimit(?int $limit): void
    {
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException('limit must be at least 1');
        }
    }
}
