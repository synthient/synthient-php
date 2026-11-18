<?php

declare(strict_types=1);

namespace Synthient\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use Synthient\Enum\Cadence;
use Synthient\Enum\DomainStatus;

/**
 * Covers lookupDomain(), which is deprecated. Delete this file when the next major version removes it.
 */
final class DomainLookupTest extends TestCase
{
    public function testLookupDomain(): void
    {
        $client = $this->client([self::json(200, Fixtures::domain()), self::json(200, Fixtures::domain()), self::json(200, Fixtures::domain())]);
        $domain = $client->lookupDomain('example.com', limit: 10);
        $client->lookupDomain('a/b?c d');
        $client->lookupDomain('example.com', limit: 5000);

        self::assertSame(self::BASE_URL . '/lookup/domain/example.com?limit=10', (string) $this->request(0)->getUri());
        self::assertSame('/api/v4/lookup/domain/a%2Fb%3Fc%20d', $this->request(1)->getUri()->getPath());
        self::assertSame('', $this->request(1)->getUri()->getQuery());
        self::assertSame('limit=5000', $this->request(2)->getUri()->getQuery());
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
        self::thrown(InvalidArgumentException::class, static fn() => $client->lookupDomain('example.com', limit: -1));
        self::assertSame([], $this->history);
    }
}
