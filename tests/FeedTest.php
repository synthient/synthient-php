<?php

declare(strict_types=1);

namespace Synthient\Tests;

use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Synthient\Enum\ExportFormat;
use Synthient\Enum\ExportKind;
use Synthient\Enum\ExportVariant;
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
        foreach ([0, -1] as $limit) {
            self::thrown(InvalidArgumentException::class, static fn() => $feed->listExports(limit: $limit));
            self::thrown(InvalidArgumentException::class, static fn() => $feed->allExports(limit: $limit));
        }
        self::assertSame([], $this->history);
    }

    public function testExportListingsLeaveLargeLimitsToTheApi(): void
    {
        $this->client([self::json(200, Fixtures::exportPage([]))])->proxies->listExports(limit: 1000);

        self::assertSame('limit=1000', $this->request()->getUri()->getQuery());
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

    public function testHeliosRoutes(): void
    {
        $client = $this->client(static function (RequestInterface $request): PromiseInterface {
            $path = $request->getUri()->getPath();

            return Create::promiseFor(match (true) {
                str_ends_with($path, '/export') => self::json(200, Fixtures::exportPage([])),
                str_ends_with($path, '/daily') => self::json(200, Fixtures::exportVolume()),
                str_ends_with($path, '/meta') => self::json(200, Fixtures::exportMeta()),
                str_ends_with($path, '/stream') => new Response(200, [], ''),
                default => new Response(307, ['Location' => 'https://storage.test/a']),
            });
        });
        foreach ([$client->heliosHttp, $client->heliosHttps, $client->heliosDns, $client->heliosAdb] as $feed) {
            $feed->listExports();
            $feed->exportVolume();
            $feed->exportMeta('2026-05-03/7');
            $feed->downloadUrl('latest');
            iterator_to_array($feed->stream());
        }

        self::assertCount(20, $this->history);
        foreach ($this->history as $entry) {
            self::assertMatchesRegularExpression('#^/api/v4/feeds/helios/(http|https|dns|adb)/#', $entry['request']->getUri()->getPath());
        }
    }

    public function testDayAndVariantAreSentOnEveryPageOnlyWhenSet(): void
    {
        $client = $this->client([
            self::json(200, Fixtures::exportPage([Fixtures::export('a')], 'c1')),
            self::json(200, Fixtures::exportPage([Fixtures::export('b')])),
            self::json(200, Fixtures::exportPage([])),
            self::json(200, Fixtures::exportPage([])),
            self::json(200, Fixtures::exportPage([])),
        ]);
        iterator_to_array($client->proxies->allExports(day: '2026-05-03', variant: ExportVariant::LITE));
        $client->proxies->listExports(limit: 5, day: new DateTimeImmutable('2026-05-03 22:30', new DateTimeZone('America/New_York')));
        $client->proxies->listExports(variant: ExportVariant::FULL);
        $client->proxies->listExports();

        self::assertSame(
            ['day=2026-05-03&variant=lite', 'cursor=c1&day=2026-05-03&variant=lite', 'limit=5&day=2026-05-04', 'variant=full', ''],
            array_map(static fn(array $entry) => $entry['request']->getUri()->getQuery(), $this->history),
        );
    }

    public function testVariantIsSentOnMetaAndDownloads(): void
    {
        $client = $this->client([
            self::json(200, ['variant' => 'lite'] + Fixtures::exportMeta()),
            self::json(200, Fixtures::exportMeta()),
            new Response(307, ['Location' => 'https://storage.test/a?sig=1']),
            new Response(307, ['Location' => 'https://storage.test/a?sig=1']),
            new Response(200, [], 'PAR1'),
        ]);
        $meta = $client->proxies->exportMeta('2026-05-03', variant: ExportVariant::LITE);
        $client->proxies->exportMeta('2026-05-03/7');
        $client->anonymizers->downloadUrl('2026-05-03/7', variant: ExportVariant::LITE);
        $client->ja4t->download('latest', variant: ExportVariant::LITE)->close();

        self::assertSame(ExportVariant::LITE, $meta->variant);
        self::assertSame(
            [
                '/api/v4/feeds/proxies/export/2026-05-03/meta?variant=lite',
                '/api/v4/feeds/proxies/export/2026-05-03/7/meta',
                '/api/v4/feeds/anonymizers/export/2026-05-03/7?variant=lite',
                '/api/v4/feeds/ja4t/export/latest?variant=lite',
                '/a?sig=1',
            ],
            array_map(static function (array $entry): string {
                $uri = $entry['request']->getUri();

                return $uri->getPath() . ($uri->getQuery() === '' ? '' : "?{$uri->getQuery()}");
            }, $this->history),
        );
    }

    public function testUnpublishedLiteFileIsAnApiError(): void
    {
        $client = $this->client([self::problem(404), self::problem(404)]);

        self::assertSame(404, self::thrown(ApiException::class, static fn() => $client->proxies->exportMeta('latest', variant: ExportVariant::LITE))->statusCode);
        self::assertSame(404, self::thrown(ApiException::class, static fn() => $client->proxies->download('latest', variant: ExportVariant::LITE))->statusCode);
    }

    public function testExportVolume(): void
    {
        $client = $this->client([self::json(200, Fixtures::exportVolume()), self::json(200, Fixtures::exportVolume())]);
        $volume = $client->proxies->exportVolume();
        $client->torrents->exportVolume(days: 7);

        self::assertSame(self::BASE_URL . '/feeds/proxies/export/daily', (string) $this->request(0)->getUri());
        self::assertSame(self::BASE_URL . '/feeds/torrents/export/daily?days=7', (string) $this->request(1)->getUri());
        self::assertSame('proxies', $volume->stream);
        self::assertSame(ExportKind::DAILY, $volume->days[0]->kind);
        self::assertSame('2026-11-16', $volume->days[0]->date);
        self::assertSame(1, $volume->days[0]->snapshots);
        self::assertSame(1_440_452_210, $volume->days[0]->rowCount);
        self::assertSame(19_629_681_638, $volume->days[0]->sizeBytes);
        self::assertSame(ExportKind::HOURLY, $volume->days[1]->kind);
        self::assertSame(3, $volume->days[1]->snapshots);
    }

    public function testExportVolumeIsNotAnExportId(): void
    {
        $client = $this->client([]);
        self::thrown(InvalidArgumentException::class, static fn() => $client->proxies->exportMeta('daily'));
        self::assertSame([], $this->history);
    }

    public function testExportFormatsAndLiteItems(): void
    {
        $lite = ['variant' => 'lite', 'download_path' => '/api/v4/feeds/proxies/export/2026-05-03?variant=lite'] + Fixtures::export();
        $page = $this->client([self::json(200, Fixtures::exportPage([Fixtures::export(), $lite]))])->proxies->listExports(variant: ExportVariant::LITE);

        self::assertSame(ExportFormat::PARQUET, $page->exports[0]->formats[0]->format);
        self::assertSame(1024, $page->exports[0]->formats[0]->sizeBytes);
        self::assertSame($page->exports[0]->checksum, $page->exports[0]->formats[0]->checksum);
        self::assertNull($page->exports[0]->variant);
        self::assertNull($page->exports[0]->entities);
        self::assertSame(ExportVariant::LITE, $page->exports[1]->variant);
        self::assertSame('2026-05-03', $page->exports[1]->id);
    }

    public function testExportsFrom1_0WithoutFormatsStillDecode(): void
    {
        $export = Fixtures::export();
        unset($export['formats']);
        $meta = Fixtures::exportMeta();
        unset($meta['formats']);
        $client = $this->client([self::json(200, Fixtures::exportPage([$export])), self::json(200, $meta)]);

        self::assertSame([], $client->proxies->listExports()->exports[0]->formats);
        self::assertSame([], $client->proxies->exportMeta('latest')->formats);
    }

    public function testUnknownFormatsAndColumnTypesDecode(): void
    {
        $meta = Fixtures::exportMeta();
        $meta['format'] = 'feather';
        $meta['formats'][0]['format'] = 'feather';
        $meta['schema']['fields'] = array_map(
            static fn(string $type) => ['name' => $type, 'type' => $type],
            ['double', 'int32', 'date', 'float', 'timestamp', 'list<string>'],
        );
        $decoded = $this->client([self::json(200, $meta)])->proxies->exportMeta('latest');

        self::assertSame('feather', $decoded->format);
        self::assertSame('feather', $decoded->formats[0]->format);
        self::assertSame(['double', 'int32', 'date', 'float', 'timestamp', 'list<string>'], array_map(static fn($column) => $column->type, $decoded->schema->fields));
    }
}
