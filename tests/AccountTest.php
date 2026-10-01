<?php

declare(strict_types=1);

namespace Synthient\Tests;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Response;
use Synthient\Enum\Relation;
use Synthient\Enum\Scope;
use Synthient\Exception\ApiException;

final class AccountTest extends TestCase
{
    public function testAccount(): void
    {
        $account = $this->client([self::json(200, Fixtures::account())])->account();

        self::assertSame('dev@example.com', $account->email);
        self::assertSame('Ada', $account->firstName);
        self::assertSame(Relation::OWNER, $account->organization->relation);
        self::assertSame([Scope::BASIC, Scope::PROXY_FEEDS], $account->scopes);
        self::assertSame(9_000_000_000, $account->lookupQuota->credits);
        self::assertSame(3600, $account->lookupQuota->resetsIn);
        self::assertNotNull($account->apiKey);
        self::assertEquals(new DateTimeImmutable('2026-09-28T19:04:52.463869Z'), $account->apiKey->createdAt);
        self::assertSame('+00:00', $account->apiKey->createdAt->format('P'));
    }

    public function testAccountWithOffsetTimestampIsConvertedToUtc(): void
    {
        $body = Fixtures::account();
        $body['api_key']['created_at'] = '2026-09-28T21:04:52+02:00';
        $account = $this->client([self::json(200, $body)])->account();

        self::assertNotNull($account->apiKey);
        self::assertSame('2026-09-28T19:04:52+00:00', $account->apiKey->createdAt->format(DATE_ATOM));
    }

    public function testHealth(): void
    {
        $client = $this->client([self::json(200, ['status' => 'ok'])]);
        $client->health();

        self::assertSame(self::BASE_URL . '/health', (string) $this->request()->getUri());
        self::assertSame(self::KEY, $this->request()->getHeaderLine('X-Api-Key'));
    }

    public function testUnhealthyApiIsNotRetried(): void
    {
        $cases = [
            self::json(503, ['status' => 'unhealthy', 'problems' => ['db']]),
            new Response(503, ['Content-Type' => 'text/html'], '<html>down</html>'),
            new Response(401, ['Content-Type' => 'text/plain'], '401 invalid api key'),
        ];
        foreach ($cases as $response) {
            $this->history = [];
            try {
                $this->client([$response, self::json(200, ['status' => 'ok'])])->health();
                self::fail('expected an API error');
            } catch (ApiException $e) {
                self::assertSame($response->getStatusCode(), $e->statusCode);
            }
            self::assertCount(1, $this->history);
        }
    }
}
