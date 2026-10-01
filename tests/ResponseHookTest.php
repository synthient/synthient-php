<?php

declare(strict_types=1);

namespace Synthient\Tests;

use GuzzleHttp\Psr7\Response;
use RuntimeException;
use Synthient\Exception\SynthientException;
use Synthient\Internal\Transport;
use Synthient\RateLimit;
use Synthient\ResponseMetadata;

final class ResponseHookTest extends TestCase
{
    public function testHookSeesEveryApiResponse(): void
    {
        /** @var list<ResponseMetadata> $seen */
        $seen = [];
        $client = $this->client([
            self::problem(503, ['X-Request-Id' => 'r1']),
            self::json(200, Fixtures::account(), ['X-Request-Id' => 'r2', 'RateLimit-Limit' => '10', 'RateLimit-Remaining' => '9', 'RateLimit-Reset' => '1']),
            new Response(307, ['Location' => 'https://storage.test/a?sig=x', 'X-Request-Id' => 'r3']),
            new Response(200, [], 'PAR1'),
            new Response(200, ['Content-Type' => 'application/json'], '{"email":'),
        ], responseHook: static function (ResponseMetadata $metadata) use (&$seen): void {
            $seen[] = $metadata;
        });

        $client->account();
        $client->proxies->download('latest')->close();
        self::thrown(SynthientException::class, $client->account(...));

        self::assertSame([503, 200, 307, 200], array_map(static fn(ResponseMetadata $m) => $m->statusCode, $seen));
        self::assertSame(['r1', 'r2', 'r3', null], array_map(static fn(ResponseMetadata $m) => $m->requestId, $seen));
        self::assertEquals(new RateLimit(10, 9, 1), $seen[1]->rateLimit);
        self::assertNull($seen[0]->rateLimit);
        self::assertSame(['https://storage.test/a?sig=x'], $seen[2]->headers['Location']);
    }

    public function testHookIsNotCalledForTransportErrors(): void
    {
        $calls = 0;
        $client = $this->client(
            [self::dropped('refused'), self::json(200, Fixtures::account())],
            responseHook: static function () use (&$calls): void {
                $calls++;
            },
        );
        $client->account();

        self::assertSame(1, $calls);
    }

    public function testHookExceptionsPropagate(): void
    {
        $client = $this->client([self::json(200, Fixtures::account())], responseHook: static function (): void {
            throw new RuntimeException('hook failed');
        });

        $this->expectExceptionMessage('hook failed');
        $client->account();
    }

    public function testParseRateLimit(): void
    {
        $headers = ['RateLimit-Limit' => '200', 'RateLimit-Remaining' => '0', 'RateLimit-Reset' => '-5'];
        self::assertEquals(new RateLimit(200, 0, 0), Transport::parseRateLimit(new Response(200, $headers)));
        self::assertNull(Transport::parseRateLimit(new Response(200)));
        self::assertNull(Transport::parseRateLimit(new Response(200, array_diff_key($headers, ['RateLimit-Remaining' => true]))));
        self::assertNull(Transport::parseRateLimit(new Response(200, ['RateLimit-Limit' => 'lots'] + $headers)));
    }
}
