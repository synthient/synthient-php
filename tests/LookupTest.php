<?php

declare(strict_types=1);

namespace Synthient\Tests;

use DateTimeImmutable;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\InvalidArgumentException as GuzzleInvalidArgumentException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\PromiseInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Synthient\Enum\Cadence;
use Synthient\Enum\DomainStatus;
use Synthient\Enum\NetworkType;

final class LookupTest extends TestCase
{
    public function testLookupIp(): void
    {
        $client = $this->client([self::json(200, Fixtures::ipInfo())]);
        $info = $client->lookupIp('8.8.8.8');

        self::assertSame(self::BASE_URL . '/lookup/ip/8.8.8.8', (string) $this->request()->getUri());
        self::assertSame('8.8.8.8', $info->ip);
        self::assertNotNull($info->network);
        self::assertSame(15169, $info->network->asn);
        self::assertSame(NetworkType::DATACENTER, $info->network->type);
        self::assertNull($info->network->org);
        self::assertNull($info->network->abuseEmail);
        self::assertNotNull($info->location);
        self::assertSame(37.0, $info->location->latitude);
        self::assertSame('9q9hv', $info->location->geoHash);
        self::assertNotNull($info->intelligence);
        self::assertSame(12, $info->intelligence->riskScore);
        self::assertSame(['DATACENTER_PROXY'], $info->intelligence->categories);
        self::assertSame('acme', $info->intelligence->providers[0]->name);
        self::assertEquals(new DateTimeImmutable('@1760000000'), $info->intelligence->devices[0]->lastSeen);
    }

    public function testLookupIpSendsTheCanonicalAddress(): void
    {
        $client = $this->client([self::json(200, Fixtures::ipInfo('2001:db8::1'))]);
        $client->lookupIp('2001:DB8:0:0::1');

        self::assertSame('/api/v4/lookup/ip/2001:db8::1', $this->request()->getUri()->getPath());
    }

    public function testMissingSectionsAreNull(): void
    {
        $client = $this->client([self::json(200, ['ip' => '10.0.0.1', 'network' => null])]);
        $info = $client->lookupIp('10.0.0.1');

        self::assertNull($info->network);
        self::assertNull($info->location);
        self::assertNull($info->intelligence);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidAddresses(): iterable
    {
        yield [''];
        yield ['8.8.8'];
        yield ['999.1.1.1'];
        yield ['fe80::1%eth0'];
        yield ['8.8.8.8/32'];
        yield ['../account/me'];
    }

    #[DataProvider('invalidAddresses')]
    public function testLookupIpRejectsInvalidAddresses(string $ip): void
    {
        $client = $this->client([]);
        self::thrown(InvalidArgumentException::class, static fn() => $client->lookupIp($ip));
        self::assertSame([], $this->history);
    }

    public function testLookupIps(): void
    {
        $client = $this->client([self::json(200, ['results' => [Fixtures::ipInfo(), Fixtures::ipInfo('1.1.1.1')]])]);
        $results = $client->lookupIps(['8.8.8.8', '1.1.1.1', '2001:DB8::1']);

        $request = $this->request();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::BASE_URL . '/lookup/ips', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('{"ips":["8.8.8.8","1.1.1.1","2001:db8::1"]}', (string) $request->getBody());
        self::assertSame(['8.8.8.8', '1.1.1.1'], array_map(static fn($result) => $result->ip, $results));
    }

    public function testLookupIpsValidatesBeforeSending(): void
    {
        $client = $this->client([]);
        self::assertSame([], $client->lookupIps([]));

        $e = self::thrown(InvalidArgumentException::class, static fn() => $client->lookupIps(array_fill(0, 1001, '8.8.8.8')));
        self::assertStringContainsString('1001', $e->getMessage());
        $e = self::thrown(InvalidArgumentException::class, static fn() => $client->lookupIps(['8.8.8.8', 'nope']));
        self::assertStringContainsString('index 1', $e->getMessage());
        self::assertSame([], $this->history);
    }

    public function testBilledLookupsForceAFreshConnection(): void
    {
        $client = $this->client([
            self::json(200, Fixtures::ipInfo()),
            self::json(200, ['results' => []]),
            self::json(200, Fixtures::domain()),
            self::json(200, Fixtures::account()),
        ]);
        $client->lookupIp('8.8.8.8');
        $client->lookupIps(['8.8.8.8']);
        $client->lookupDomain('example.com');
        $client->account();

        foreach ([0, 1, 2] as $index) {
            self::assertSame([CURLOPT_FRESH_CONNECT => true], $this->history[$index]['options']['curl'] ?? null);
        }
        self::assertArrayNotHasKey('curl', $this->history[3]['options']);
    }

    public function testBilledLookupsDropTheCurlOptionWhenTheHandlerRejectsIt(): void
    {
        $mock = new MockHandler([self::json(200, Fixtures::ipInfo())]);
        $calls = 0;
        $handler = static function (RequestInterface $request, array $options) use ($mock, &$calls): PromiseInterface {
            $calls++;
            if (isset($options['curl'])) {
                throw new GuzzleInvalidArgumentException('Passing the "curl" request option to the stream handler is not supported');
            }

            return $mock($request, $options);
        };

        self::assertSame('8.8.8.8', $this->client($handler)->lookupIp('8.8.8.8')->ip);
        self::assertSame(2, $calls);
        self::assertCount(1, $this->history);
        self::assertArrayNotHasKey('curl', $this->history[0]['options']);
    }

    public function testBilledLookupsFailWhenTheHandlerRequiresConnectionReuse(): void
    {
        $calls = 0;
        $handler = static function () use (&$calls): PromiseInterface {
            $calls++;

            throw new GuzzleInvalidArgumentException('The CURLOPT_FRESH_CONNECT cURL option cannot be used when persistent cURL sharing is required because it disables connection reuse.');
        };

        $e = self::thrown(InvalidArgumentException::class, fn() => $this->client($handler)->lookupIp('8.8.8.8'));
        self::assertNotInstanceOf(GuzzleException::class, $e);
        self::assertStringContainsString('persistent cURL sharing', $e->getMessage());
        self::assertSame(1, $calls);
    }

    public function testLookupDomain(): void
    {
        $client = $this->client([self::json(200, Fixtures::domain()), self::json(200, Fixtures::domain())]);
        $domain = $client->lookupDomain('example.com', limit: 10);
        $client->lookupDomain('a/b?c d');

        self::assertSame(self::BASE_URL . '/lookup/domain/example.com?limit=10', (string) $this->request(0)->getUri());
        self::assertSame('/api/v4/lookup/domain/a%2Fb%3Fc%20d', $this->request(1)->getUri()->getPath());
        self::assertSame('', $this->request(1)->getUri()->getQuery());
        self::assertSame(DomainStatus::ACTIVE, $domain->status);
        self::assertSame(Cadence::BURST_DRIVEN, $domain->activityStats->cadence);
        self::assertSame(5, $domain->stats->events24h);
        self::assertSame([0, 1, 1], $domain->uniqueIps->sparkline24h);
        self::assertCount(7, $domain->hourDowHeatmap);
        self::assertCount(24, $domain->hourDowHeatmap[6]);
        self::assertNotNull($domain->topAsn);
        self::assertSame(4_294_967_295, $domain->topAsn->asn);
        self::assertSame('203.0.113.7', $domain->recentEvents[0]->sourceIp);
        self::assertEquals(new DateTimeImmutable('@1759968000'), $domain->timeSeries[0]->date);
    }

    public function testInactiveDomainHasNoTopAsn(): void
    {
        $body = Fixtures::domain();
        unset($body['top_asn']);
        $body['status'] = 'inactive';
        $domain = $this->client([self::json(200, $body)])->lookupDomain('example.com');

        self::assertSame(DomainStatus::INACTIVE, $domain->status);
        self::assertNull($domain->topAsn);
    }

    public function testLookupDomainValidatesBeforeSending(): void
    {
        $client = $this->client([]);
        foreach (['', '.', '..'] as $domain) {
            self::thrown(InvalidArgumentException::class, static fn() => $client->lookupDomain($domain));
        }
        self::thrown(InvalidArgumentException::class, static fn() => $client->lookupDomain('example.com', limit: 0));
        self::thrown(InvalidArgumentException::class, static fn() => $client->lookupDomain('example.com', limit: 1001));
        self::assertSame([], $this->history);
    }
}
