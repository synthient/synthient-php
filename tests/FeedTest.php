<?php

declare(strict_types=1);

namespace Synthient\Tests;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Synthient\Enum\ExportKind;
use Synthient\Enum\ParquetType;
use Synthient\Exception\ApiException;
use Synthient\Exception\SynthientException;

final class FeedTest extends TestCase
{
    public function testFeedRoutes(): void
    {
        $client = $this->client(array_map(static fn() => self::json(200, Fixtures::exportMeta()), range(1, 8)));
        $feeds = self::feeds($client);
        foreach ($feeds as $feed) {
            $feed->exportMeta('latest');
        }

        $paths = array_map(static fn(array $entry) => $entry['request']->getUri()->getPath(), $this->history);
        self::assertSame(array_map(static fn(string $name) => "/api/v4/feeds/{$name}/export/latest/meta", array_keys($feeds)), $paths);
    }

    /**
     * @return iterable<array{string|DateTimeImmutable, string}>
     */
    public static function exportIds(): iterable
    {
        yield ['latest', 'latest'];
        yield ['2026-05-03', '2026-05-03'];
        yield ['2026-05-03/7', '2026-05-03/7'];
        yield ['2026-05-03/07', '2026-05-03/7'];
        yield ['2026-05-03/23', '2026-05-03/23'];
        yield [new DateTimeImmutable('2026-05-03 22:30', new DateTimeZone('America/New_York')), '2026-05-04'];
    }

    #[DataProvider('exportIds')]
    public function testExportIds(string|DateTimeImmutable $id, string $path): void
    {
        $this->client([self::json(200, Fixtures::exportMeta())])->proxies->exportMeta($id);

        self::assertSame("/api/v4/feeds/proxies/export/{$path}/meta", $this->request()->getUri()->getPath());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidExportIds(): iterable
    {
        foreach (['', 'latest/3', '2026-05-03/24', '2026-05-03/', '2026-5-3', '2026-02-30', '../account/me', '2026-05-03/07/meta', '2026-05-03/-1'] as $id) {
            yield [$id];
        }
    }

    #[DataProvider('invalidExportIds')]
    public function testRejectsInvalidExportIds(string $id): void
    {
        $client = $this->client([]);
        self::thrown(InvalidArgumentException::class, static fn() => $client->proxies->exportMeta($id));
        self::assertSame([], $this->history);
    }

    public function testExportMeta(): void
    {
        $meta = $this->client([self::json(200, Fixtures::exportMeta())])->proxies->exportMeta('2026-05-03/7');

        self::assertSame(ExportKind::HOURLY, $meta->kind);
        self::assertSame(7, $meta->hour);
        self::assertSame('2026-05-03T07:00:00+00:00', $meta->date->format(DATE_ATOM));
        self::assertSame(ParquetType::UINT32, $meta->schema->fields[1]->type);
    }

    public function testListExports(): void
    {
        $page = $this->client([self::json(200, Fixtures::exportPage([Fixtures::export('2026-05-03/7'), Fixtures::export()], 'c2'))])
            ->proxies->listExports(limit: 2, cursor: 'c1');

        self::assertSame(self::BASE_URL . '/feeds/proxies/export?cursor=c1&limit=2', (string) $this->request()->getUri());
        self::assertSame('c2', $page->nextCursor);
        self::assertSame(7, $page->exports[0]->hour);
        self::assertNull($page->exports[1]->hour);
        self::assertSame(ExportKind::DAILY, $page->exports[1]->kind);
        self::assertSame('2026-05-03', $page->exports[1]->date);
        self::assertSame(1_777_852_800, $page->exports[1]->createdAt->getTimestamp());
    }

    public function testExportListingsRejectInvalidLimits(): void
    {
        $feed = $this->client([])->proxies;
        foreach ([0, 501] as $limit) {
            self::thrown(InvalidArgumentException::class, static fn() => $feed->listExports(limit: $limit));
            self::thrown(InvalidArgumentException::class, static fn() => $feed->allExports(limit: $limit));
        }
        self::assertSame([], $this->history);
    }

    public function testAllExportsFollowsCursorsThroughEmptyPages(): void
    {
        $client = $this->client([
            self::json(200, Fixtures::exportPage([Fixtures::export('a')], 'c1')),
            self::json(200, Fixtures::exportPage([], 'c2')),
            self::json(200, Fixtures::exportPage([Fixtures::export('b'), Fixtures::export('c')])),
        ]);
        $ids = [];
        foreach ($client->proxies->allExports(limit: 1) as $key => $export) {
            $ids[$key] = $export->id;
        }

        self::assertSame(['a', 'b', 'c'], $ids);
        self::assertSame(['limit=1', 'cursor=c1&limit=1', 'cursor=c2&limit=1'], array_map(static fn(array $entry) => $entry['request']->getUri()->getQuery(), $this->history));
    }

    public function testAllExportsIsLazyAndStopsOnBreak(): void
    {
        $client = $this->client([
            self::json(200, Fixtures::exportPage([Fixtures::export('a'), Fixtures::export('b')], 'c1')),
            self::json(200, Fixtures::exportPage([Fixtures::export('c')])),
        ]);
        $exports = $client->proxies->allExports();
        self::assertCount(0, $this->history);

        foreach ($exports as $export) {
            break;
        }
        self::assertCount(1, $this->history);
    }

    public function testAllExportsRejectsARepeatedCursor(): void
    {
        $client = $this->client([
            self::json(200, Fixtures::exportPage([Fixtures::export('a')], 'c1')),
            self::json(200, Fixtures::exportPage([Fixtures::export('b')], 'c1')),
        ]);
        [$exports, $error] = self::drain($client->proxies->allExports());

        self::assertSame(['a', 'b'], array_map(static fn($export) => $export->id, $exports));
        self::assertInstanceOf(SynthientException::class, $error);
        self::assertStringContainsString('repeated cursor', $error->getMessage());
    }

    public function testAllExportsSurfacesLaterPageErrors(): void
    {
        $client = $this->client([self::json(200, Fixtures::exportPage([Fixtures::export('a')], 'c1')), self::problem(403)]);
        [$exports, $error] = self::drain($client->proxies->allExports());

        self::assertSame(['a'], array_map(static fn($export) => $export->id, $exports));
        self::assertInstanceOf(ApiException::class, $error);
        self::assertSame(403, $error->statusCode);
    }

    public function testAbsentEmptyAndNullCursorsEndTheListing(): void
    {
        $client = $this->client([
            self::json(200, Fixtures::exportPage([Fixtures::export('a')])),
            self::json(200, Fixtures::exportPage([Fixtures::export('b')], '')),
            self::json(200, ['next_cursor' => null] + Fixtures::exportPage([Fixtures::export('c')])),
        ]);

        for ($i = 0; $i < 3; $i++) {
            self::assertCount(1, iterator_to_array($client->proxies->allExports()));
        }
        self::assertCount(3, $this->history);
    }
}
