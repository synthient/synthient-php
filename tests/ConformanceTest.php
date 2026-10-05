<?php

declare(strict_types=1);

namespace Synthient\Tests;

use DateTimeImmutable;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use ReflectionParameter;
use Synthient\Client;
use Synthient\Enum;
use Synthient\Event;
use Synthient\Internal\Decoder;
use Synthient\Internal\IpBatch;
use Synthient\Model;

/**
 * Checks the SDK against the OpenAPI spec it was built from, .sdkgen/openapi.json.
 */
final class ConformanceTest extends TestCase
{
    private const array MODELS = [
        'AccountInfoResponse' => Model\Account::class,
        'Organization' => Model\Organization::class,
        'LookupQuota' => Model\LookupQuota::class,
        'AccountAPIKey' => Model\AccountApiKey::class,
        'IpProvidersResponse' => Model\IpInfo::class,
        'IpBatchResponse' => IpBatch::class,
        'NetworkInfo' => Model\Network::class,
        'LocationInfo' => Model\Location::class,
        'IntelligenceInfo' => Model\Intelligence::class,
        'ProviderEntry' => Model\Provider::class,
        'DeviceInfo' => Model\Device::class,
        'DomainLookupResponse' => Model\DomainInfo::class,
        'Stats' => Model\DomainStats::class,
        'UniqueIPsBlock' => Model\UniqueIps::class,
        'TimeSeriesEntry' => Model\TimeSeriesPoint::class,
        'ASNEntry' => Model\AsnCount::class,
        'SubdomainEntry' => Model\SubdomainCount::class,
        'PortEntry' => Model\PortCount::class,
        'CountryEntry' => Model\CountryCount::class,
        'ActivityStatsBlock' => Model\ActivityStats::class,
        'RecentEvent' => Model\DomainEvent::class,
        'ExportListResponse' => Model\ExportPage::class,
        'ExportListItem' => Model\Export::class,
        'ExportMetaResponse' => Model\ExportMeta::class,
        'Schema' => Model\ParquetSchema::class,
        'ProjectedColumn' => Model\ParquetColumn::class,
        'ProxyStreamEntry' => Event\ProxyEvent::class,
        'AnonymizerStreamEntry' => Event\AnonymizerEvent::class,
        'TorrentStreamEntry' => Event\TorrentEvent::class,
        'TorrentFileEntry' => Event\TorrentFile::class,
        'TorrentPeerEntry' => Event\TorrentPeer::class,
        'JA4TStreamEntry' => Event\Ja4tEvent::class,
        'HoneypotMeta' => Event\HeliosMeta::class,
        'HoneypotHTTPStreamEntry' => Event\HeliosHttpEvent::class,
        'HoneypotHTTPSStreamEntry' => Event\HeliosHttpsEvent::class,
        'HoneypotDNSStreamEntry' => Event\HeliosDnsEvent::class,
        'HoneypotADBStreamEntry' => Event\HeliosAdbEvent::class,
        'TLSClientHello' => Event\TlsClientHello::class,
        'TLSCodeName' => Event\TlsCodeName::class,
        'TLSExtension' => Event\TlsExtension::class,
    ];

    /** Schemas the SDK handles without a model: the batch request body and the errors. */
    private const array UNMODELED = ['IpBatchRequest', 'ProblemDetails', 'ValidationProblemDetails'];

    private const array VALUE_SETS = [
        'AccountInfoResponse.scopes' => Enum\Scope::class,
        'ActivityStatsBlock.cadence' => Enum\Cadence::class,
        'AnonymizerStreamEntry.type' => Enum\ProxyType::class,
        'DeviceInfo.os' => Enum\OperatingSystem::class,
        'DomainLookupResponse.status' => Enum\DomainStatus::class,
        'ExportListItem.kind' => Enum\ExportKind::class,
        'ExportMetaResponse.kind' => Enum\ExportKind::class,
        'JA4TStreamEntry.type' => Enum\ProxyType::class,
        'NetworkInfo.type' => Enum\NetworkType::class,
        'Organization.relation' => Enum\Relation::class,
        'ProjectedColumn.type' => Enum\ParquetType::class,
        'ProxyStreamEntry.type' => Enum\ProxyType::class,
    ];

    /** @var array<string, mixed>|null */
    private static ?array $spec = null;

    /**
     * @return array<string, mixed>
     */
    private static function spec(): array
    {
        /** @var array<string, mixed> */
        return self::$spec ??= json_decode((string) file_get_contents(__DIR__ . '/../.sdkgen/openapi.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function schemas(): array
    {
        $schemas = self::spec()['components']['schemas'] ?? null;
        self::assertIsArray($schemas);

        /** @var array<string, array<string, mixed>> $schemas */
        return $schemas;
    }

    public function testUsesTheSpecServer(): void
    {
        self::assertSame([Client::DEFAULT_BASE_URL], array_column(self::spec()['servers'], 'url'));
    }

    public function testEverySchemaIsModeled(): void
    {
        $names = array_keys(self::schemas());
        sort($names);
        $handled = [...array_keys(self::MODELS), ...self::UNMODELED];
        sort($handled);

        self::assertSame($names, $handled);
    }

    public function testModelsMatchTheirSchemas(): void
    {
        $schemas = self::schemas();
        foreach (self::MODELS as $name => $class) {
            $this->assertMatchesSchema($class, $schemas[$name], $name);
        }
        $details = $schemas['HoneypotHTTPStreamEntry']['properties']['details'] ?? null;
        self::assertIsArray($details);
        $this->assertMatchesSchema(Event\HttpRequestDetails::class, $details, 'HoneypotHTTPStreamEntry.details');
    }

    public function testValueSetsMatchTheSpec(): void
    {
        $found = [];
        foreach (self::schemas() as $name => $schema) {
            foreach ($schema['properties'] ?? [] as $property => $definition) {
                \assert(\is_array($definition));
                $values = $definition['enum'] ?? $definition['x-extensible-enum'] ?? $definition['items']['enum'] ?? $definition['items']['x-extensible-enum'] ?? null;
                if (\is_array($values) && \count($values) > 1) {
                    $found["{$name}.{$property}"] = $values;
                }
            }
        }
        ksort($found);
        $expected = self::VALUE_SETS;
        ksort($expected);

        self::assertSame(array_keys($expected), array_keys($found));
        foreach ($found as $path => $values) {
            self::assertSame($values, array_values((new ReflectionClass($expected[$path]))->getConstants()), $path);
        }
    }

    public function testEveryOperationIsCovered(): void
    {
        $expected = [];
        foreach (self::spec()['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                $expected[] = strtoupper($method) . ' /api/v4' . strtr($path, [
                    '{ip_address}' => '8.8.8.8',
                    '{domain}' => 'example.com',
                    '{date}' => '2026-05-03',
                    '{hour}' => '7',
                ]);
            }
        }

        $handler = static function (RequestInterface $request): PromiseInterface {
            $path = $request->getUri()->getPath();

            return Create::promiseFor(match (true) {
                str_ends_with($path, '/stream') => new Response(200, [], ''),
                str_ends_with($path, '/meta') => self::json(200, Fixtures::exportMeta()),
                str_ends_with($path, '/export') => self::json(200, Fixtures::exportPage([])),
                str_contains($path, '/export/') => new Response(307, ['Location' => 'https://storage.test/a']),
                str_ends_with($path, '/account/me') => self::json(200, Fixtures::account()),
                str_ends_with($path, '/health') => self::json(200, ['status' => 'ok']),
                str_ends_with($path, '/lookup/ips') => self::json(200, ['results' => []]),
                str_contains($path, '/lookup/ip/') => self::json(200, Fixtures::ipInfo()),
                default => self::json(200, Fixtures::domain()),
            });
        };
        $client = $this->client($handler);

        $client->account();
        $client->health();
        $client->lookupIp('8.8.8.8');
        $client->lookupIps(['8.8.8.8']);
        $client->lookupDomain('example.com');
        foreach (self::feeds($client) as $feed) {
            $feed->listExports();
            $feed->exportMeta('2026-05-03');
            $feed->exportMeta('2026-05-03/7');
            $feed->downloadUrl('2026-05-03');
            $feed->downloadUrl('2026-05-03/7');
            iterator_to_array($feed->stream());
        }
        $sent = array_map(static fn(array $entry) => $entry['request']->getMethod() . ' ' . $entry['request']->getUri()->getPath(), $this->history);

        sort($expected);
        sort($sent);
        self::assertCount(53, $expected);
        self::assertSame($expected, $sent);
    }

    /**
     * @param class-string $class
     * @param array<string, mixed> $schema
     */
    private function assertMatchesSchema(string $class, array $schema, string $name): void
    {
        $properties = $schema['properties'] ?? [];
        \assert(\is_array($properties));
        $required = $schema['required'] ?? [];
        \assert(\is_array($required));
        $keys = [];
        foreach (Decoder::fields($class) as ['param' => $param, 'key' => $key, 'type' => $type, 'nullable' => $nullable]) {
            $keys[] = $key;
            $at = "{$name}.{$key}";
            self::assertArrayHasKey($key, $properties, "{$at} is not in the spec");
            $definition = $properties[$key];
            \assert(\is_array($definition));

            self::assertSame(!\in_array($key, $required, true) || ($definition['nullable'] ?? false), $nullable, "{$at} nullability");
            if ($nullable) {
                self::assertTrue((new ReflectionParameter([$class, '__construct'], $param))->isDefaultValueAvailable(), "{$at} should default to null");
            }
            $timestamp = ($definition['type'] ?? null) === 'integer' && \in_array($key, ['timestamp', 'last_seen', 'created_at', 'date'], true);
            self::assertContains($type, $timestamp ? [DateTimeImmutable::class] : self::phpTypes($definition), "{$at} type");
        }
        $missing = array_diff(array_keys($properties), $keys);
        self::assertSame([], array_values($missing), "{$name} is missing properties");
    }

    /**
     * Returns the PHP types that can hold a schema.
     *
     * @param array<mixed> $definition
     *
     * @return list<string>
     */
    private static function phpTypes(array $definition): array
    {
        $ref = $definition['$ref'] ?? $definition['allOf'][0]['$ref'] ?? null;
        if (\is_string($ref)) {
            return [self::MODELS[substr($ref, \strlen('#/components/schemas/'))]];
        }

        return match ($definition['type'] ?? null) {
            'string' => ($definition['format'] ?? null) === 'date-time' ? [DateTimeImmutable::class] : ['string'],
            'integer' => ($definition['minimum'] ?? null) === 0 ? ['int', 'uint64'] : ['int'],
            'number' => ['float'],
            'boolean' => ['bool'],
            'array' => array_map(static fn(string $item) => "list<{$item}>", self::phpTypes(\is_array($definition['items'] ?? null) ? $definition['items'] : [])),
            'object' => \is_array($definition['additionalProperties'] ?? null)
                ? array_map(static fn(string $item) => "map<{$item}>", self::phpTypes($definition['additionalProperties']))
                : [Event\HttpRequestDetails::class],
            default => [],
        };
    }
}
