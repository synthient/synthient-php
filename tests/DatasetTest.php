<?php

declare(strict_types=1);

namespace Synthient\Tests;

use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Synthient\Enum\ExportFormat;
use Synthient\Enum\ParquetType;
use Synthient\Enum\WhoisEntity;
use Synthient\Exception\ApiException;

final class DatasetTest extends TestCase
{
    private const string SIGNED = 'https://storage.test/geolocation/2026-11-17.mmdb?X-Signature=secret-signature';

    public function testRoutes(): void
    {
        $client = $this->client([
            self::json(200, ['stream' => 'geolocation', 'feeds' => []]),
            self::json(200, ['stream' => 'whois', 'feeds' => []]),
            self::json(200, ['stream' => 'whois', 'days' => []]),
            self::json(200, Fixtures::whoisMeta()),
            new Response(307, ['Location' => self::SIGNED]),
        ]);
        $client->geolocation->listExports(limit: 1, cursor: 'c1', day: '2026-11-17');
        $client->whois->listExports();
        $client->whois->exportVolume(days: 30);
        $client->whois->exportMeta('latest');
        $url = $client->geolocation->downloadUrl('2026-11-17');

        self::assertSame(
            [
                self::BASE_URL . '/feeds/geolocation/export?cursor=c1&limit=1&day=2026-11-17',
                self::BASE_URL . '/feeds/whois/export',
                self::BASE_URL . '/feeds/whois/export/daily?days=30',
                self::BASE_URL . '/feeds/whois/export/latest/meta',
                self::BASE_URL . '/feeds/geolocation/export/2026-11-17',
            ],
            array_map(static fn(array $entry) => (string) $entry['request']->getUri(), $this->history),
        );
        self::assertSame(self::SIGNED, $url);
    }

    public function testFormatIsSentOnlyWhenSet(): void
    {
        $client = $this->client([
            self::json(200, ['format' => 'csv'] + Fixtures::exportMeta()),
            new Response(307, ['Location' => self::SIGNED]),
            new Response(307, ['Location' => self::SIGNED]),
            new Response(307, ['Location' => self::SIGNED]),
        ]);
        $meta = $client->geolocation->exportMeta('latest', format: ExportFormat::CSV);
        $client->geolocation->downloadUrl('latest', format: ExportFormat::MMDB);
        $client->whois->downloadUrl('latest', format: ExportFormat::TAR_GZ);
        $client->geolocation->downloadUrl('latest');

        self::assertSame(ExportFormat::CSV, $meta->format);
        self::assertSame(
            ['format=csv', 'format=mmdb', 'format=tar.gz', ''],
            array_map(static fn(array $entry) => $entry['request']->getUri()->getQuery(), $this->history),
        );
    }

    /**
     * @return iterable<array{string|DateTimeImmutable, string}>
     */
    public static function exportIds(): iterable
    {
        yield ['latest', 'latest'];
        yield ['2026-11-17', '2026-11-17'];
        yield [new DateTimeImmutable('2026-11-17 22:30', new DateTimeZone('America/New_York')), '2026-11-18'];
    }

    #[DataProvider('exportIds')]
    public function testExportIds(string|DateTimeImmutable $id, string $path): void
    {
        $this->client([self::json(200, Fixtures::whoisMeta())])->whois->exportMeta($id);

        self::assertSame("/api/v4/feeds/whois/export/{$path}/meta", $this->request()->getUri()->getPath());
    }

    public function testRejectsHourlyIdsBeforeAnyRequest(): void
    {
        $client = $this->client([]);
        foreach (['2026-11-17/7', '2026-11-17/0', '2026-02-30', 'daily'] as $id) {
            self::thrown(InvalidArgumentException::class, static fn() => $client->geolocation->exportMeta($id));
            self::thrown(InvalidArgumentException::class, static fn() => $client->whois->downloadUrl($id));
            self::thrown(InvalidArgumentException::class, static fn() => $client->geolocation->download($id));
        }
        self::assertSame([], $this->history);
    }

    public function testDownloadSendsNoCredentialsToStorage(): void
    {
        $client = $this->client(
            [
                new Response(307, ['Location' => self::SIGNED]),
                new Response(302, ['Location' => 'https://cdn.storage.test/a.mmdb?sig=secret-signature']),
                new Response(200, [], 'MMDB'),
            ],
            [
                'headers' => ['X-Default' => 'default-secret'],
                'cookies' => CookieJar::fromArray(['session' => 'cookie-secret'], 'storage.test'),
                'auth' => ['user', 'pass'],
            ],
        );

        $body = $client->geolocation->download('2026-11-17', format: ExportFormat::MMDB);
        self::assertSame('MMDB', (string) $body);
        $body->close();

        self::assertSame(self::BASE_URL . '/feeds/geolocation/export/2026-11-17?format=mmdb', (string) $this->request(0)->getUri());
        foreach ([1, 2] as $index) {
            $request = $this->request($index);
            self::assertFalse($request->hasHeader('X-Api-Key'));
            self::assertFalse($request->hasHeader('X-Default'));
            self::assertFalse($request->hasHeader('Cookie'));
            self::assertFalse($request->hasHeader('Authorization'));
            self::assertTrue($this->history[$index]['options']['stream']);
            self::assertFalse($this->history[$index]['options']['allow_redirects']);
        }
    }

    public function testMissingFormatIsAnApiError(): void
    {
        $client = $this->client([self::problem(404)]);

        $e = self::thrown(ApiException::class, static fn() => $client->whois->download('latest', format: ExportFormat::CSV));
        self::assertSame(404, $e->statusCode);
    }

    public function testListing(): void
    {
        $export = [
            'id' => '2026-11-17',
            'kind' => 'daily',
            'date' => '2026-11-17',
            'size_bytes' => 466_431_938,
            'row_count' => 27_456_467,
            'checksum' => str_repeat('97', 32),
            'created_at' => 1_794_960_045,
            'download_path' => '/api/v4/feeds/geolocation/export/2026-11-17',
            'formats' => [
                ['format' => 'parquet', 'size_bytes' => 466_431_938, 'checksum' => str_repeat('97', 32)],
                ['format' => 'csv', 'size_bytes' => 540_196_440, 'checksum' => str_repeat('de', 32)],
                ['format' => 'mmdb', 'size_bytes' => 1_874_587_084, 'checksum' => str_repeat('ae', 32)],
            ],
        ];
        $page = $this->client([self::json(200, ['stream' => 'geolocation', 'feeds' => [$export]])])->geolocation->listExports();

        self::assertSame(
            [ExportFormat::PARQUET, ExportFormat::CSV, ExportFormat::MMDB],
            array_map(static fn($file) => $file->format, $page->exports[0]->formats),
        );
        self::assertSame(1_874_587_084, $page->exports[0]->formats[2]->sizeBytes);
    }

    public function testWhoisMeta(): void
    {
        $meta = $this->client([self::json(200, Fixtures::whoisMeta())])->whois->exportMeta('latest');

        self::assertSame(ExportFormat::TAR_GZ, $meta->format);
        self::assertSame([], $meta->schema->fields);
        self::assertSame(ExportFormat::TAR_GZ, $meta->formats[0]->format);
        self::assertNotNull($meta->entities);
        self::assertSame(WhoisEntity::ASNS, $meta->entities[0]->entity);
        self::assertSame(123_778, $meta->entities[0]->rowCount);
        self::assertSame($meta->rows, $meta->entities[0]->rowCount + $meta->entities[1]->rowCount);
        self::assertSame(ParquetType::INT64, $meta->entities[0]->schema?->fields[0]->type);
        self::assertSame('timestamp', $meta->entities[0]->schema->fields[1]->type);
        self::assertSame('list<string>', $meta->entities[1]->schema?->fields[0]->type);
    }

    public function testWhoisMetaWithNullOrMissingSchemaFields(): void
    {
        $null = Fixtures::whoisMeta();
        $null['schema'] = ['fields' => null];
        $missing = Fixtures::whoisMeta();
        $missing['schema'] = [];
        $client = $this->client([self::json(200, $null), self::json(200, $missing)]);

        self::assertSame([], $client->whois->exportMeta('latest')->schema->fields);
        self::assertSame([], $client->whois->exportMeta('latest')->schema->fields);
    }

    public function testUnknownEntityDecodes(): void
    {
        $meta = Fixtures::whoisMeta();
        $meta['entities'][0]['entity'] = 'routes';
        unset($meta['entities'][0]['schema']);
        $decoded = $this->client([self::json(200, $meta)])->whois->exportMeta('latest');

        self::assertSame('routes', $decoded->entities[0]->entity ?? null);
        self::assertNull($decoded->entities[0]->schema ?? null);
    }
}
