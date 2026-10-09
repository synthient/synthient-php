<?php

declare(strict_types=1);

namespace Synthient\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Synthient\Exception\ApiException;
use Synthient\Exception\ConnectionException;
use Synthient\Exception\TimeoutException;
use Synthient\Internal\Transport;

final class RetryTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, list<int|array{int, string}>, int, int}>
     */
    public static function policies(): iterable
    {
        yield 'free operation retries server errors' => [false, [503, 502, 200], 3, 200];
        yield 'free operation gives up after the retries' => [false, [503, 503, 503, 200], 3, 503];
        yield 'free operation does not retry 400' => [false, [400, 200], 1, 400];
        yield 'free operation retries 429 with Retry-After' => [false, [[429, '0'], 200], 2, 200];
        yield 'free operation does not retry 429 without Retry-After' => [false, [429, 200], 1, 429];
        yield 'billed lookup retries 429 with Retry-After' => [true, [[429, '1'], 200], 2, 200];
        yield 'billed lookup does not retry 429 without Retry-After' => [true, [429, 200], 1, 429];
        yield 'billed lookup does not retry 500' => [true, [500, 200], 1, 500];
        yield 'billed lookup does not retry 503' => [true, [503, 200], 1, 503];
    }

    /**
     * @param list<int|array{int, string}> $statuses
     */
    #[DataProvider('policies')]
    public function testRetryPolicy(bool $billed, array $statuses, int $requests, int $finalStatus): void
    {
        $responses = [];
        foreach ($statuses as $status) {
            [$code, $retryAfter] = \is_array($status) ? $status : [$status, null];
            $responses[] = match (true) {
                $code === 200 => self::json(200, $billed ? Fixtures::ipInfo() : Fixtures::account()),
                $retryAfter === null => self::problem($code),
                default => self::problem($code, ['Retry-After' => $retryAfter]),
            };
        }
        $client = $this->client($responses);
        $status = 200;
        try {
            $billed ? $client->lookupIp('8.8.8.8') : $client->account();
        } catch (ApiException $e) {
            $status = $e->statusCode;
        }

        self::assertCount($requests, $this->history);
        self::assertSame($finalStatus, $status);
        self::assertCount($requests - 1, $this->sleeps);
    }

    public function testRetryAfterIsAMinimumDelay(): void
    {
        $this->client([self::problem(503, ['Retry-After' => '3']), self::json(200, Fixtures::account())])->account();

        self::assertGreaterThanOrEqual(3.0, $this->sleeps[0]);
    }

    public function testLongRetryAfterIsNotWaitedFor(): void
    {
        $e = self::thrown(ApiException::class, fn() => $this->client([self::problem(429, ['Retry-After' => '120']), self::json(200, Fixtures::account())])->account());
        self::assertSame(120, $e->retryAfter);
        self::assertCount(1, $this->history);
        self::assertSame([], $this->sleeps);
    }

    public function testZeroRetries(): void
    {
        self::thrown(ApiException::class, fn() => $this->client([self::problem(503), self::json(200, Fixtures::account())], maxRetries: 0)->account());
        self::assertCount(1, $this->history);
    }

    public function testBilledRetriesResendIdenticalBodies(): void
    {
        $client = $this->client([self::problem(429, ['Retry-After' => '0']), self::json(200, ['results' => []])]);
        $client->lookupIps(['8.8.8.8', '1.1.1.1']);

        self::assertCount(2, $this->history);
        self::assertSame('{"ips":["8.8.8.8","1.1.1.1"]}', (string) $this->request(0)->getBody());
        self::assertSame('{"ips":["8.8.8.8","1.1.1.1"]}', (string) $this->request(1)->getBody());
    }

    public function testTransportErrorsAreRetriedForFreeOperations(): void
    {
        $account = $this->client([self::dropped(), self::json(200, Fixtures::account())])->account();

        self::assertSame('Ada', $account->firstName);
        self::assertCount(2, $this->history);
    }

    public function testTransportErrorsAreNotRetriedForBilledLookups(): void
    {
        $e = self::thrown(ConnectionException::class, fn() => $this->client([self::dropped(), self::json(200, Fixtures::ipInfo())])->lookupIp('8.8.8.8'));

        self::assertNotInstanceOf(ConnectException::class, $e->getPrevious());
        self::assertSame('cURL error 56: Connection reset by peer', $e->getPrevious()?->getMessage());
        self::assertCount(1, $this->history);
    }

    public function testTransportErrorTracesHoldNoRequest(): void
    {
        $args = self::traceArguments(ConnectionException::class, fn() => $this->client([self::dropped()], maxRetries: 0)->account());

        self::assertNotSame([], $args);
        foreach ($args as $arg) {
            self::assertNotInstanceOf(GuzzleException::class, $arg);
            self::assertNotInstanceOf(RequestInterface::class, $arg);
        }
    }

    public function testTlsErrorsAreNotRetried(): void
    {
        self::thrown(ConnectionException::class, fn() => $this->client([self::dropped('cURL error 60: SSL certificate problem: unable to get local issuer certificate'), self::json(200, Fixtures::account())])->account());

        self::assertCount(1, $this->history);
    }

    public function testTimeoutsAreRetriedForFreeOperations(): void
    {
        self::thrown(TimeoutException::class, fn() => $this->client([self::dropped('cURL error 28: Operation timed out after 10001 milliseconds'), self::dropped('cURL error 28: Operation timed out')], maxRetries: 1)->account());

        self::assertCount(2, $this->history);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function timeoutMessages(): iterable
    {
        yield 'operation' => ['cURL error 28: Operation timed out after 10001 milliseconds with 0 bytes received'];
        yield 'connect' => ['cURL error 28: Connection timeout after 10001 ms'];
        yield 'tls handshake' => ['cURL error 28: SSL connection timeout'];
        yield 'resolve' => ['cURL error 28: Resolving timed out after 10000 milliseconds'];
        yield 'stream handler' => ['Connection timed out'];
    }

    #[DataProvider('timeoutMessages')]
    public function testTimeoutsAreRecognized(string $message): void
    {
        $e = self::thrown(TimeoutException::class, fn() => $this->client([self::dropped($message)], maxRetries: 0)->account());

        self::assertSame('synthient: request timed out', $e->getMessage());
    }

    public function testTypedGuzzleTimeoutsAreRecognized(): void
    {
        if (!class_exists(ConnectTimeoutException::class)) {
            self::markTestSkipped('Guzzle 7 has no typed timeout exceptions');
        }
        $timeout = new ConnectTimeoutException('connect deadline exceeded', new Request('GET', self::BASE_URL));

        $e = self::thrown(TimeoutException::class, fn() => $this->client([$timeout], maxRetries: 0)->account());

        self::assertSame('synthient: request timed out', $e->getMessage());
    }

    public function testBackoff(): void
    {
        foreach ([0 => 1, 1 => 2, 4 => 16, 5 => 30, 9 => 30] as $attempt => $base) {
            for ($i = 0; $i < 50; $i++) {
                $delay = Transport::backoff($attempt);
                self::assertGreaterThanOrEqual($base * 0.75, $delay);
                self::assertLessThanOrEqual($base * 1.25, $delay);
            }
        }
    }

    public function testParseRetryAfter(): void
    {
        self::assertSame(0, Transport::parseRetryAfter('0'));
        self::assertSame(7, Transport::parseRetryAfter(' 7 '));
        self::assertSame(0, Transport::parseRetryAfter('-3'));
        self::assertSame(2 ** 31, Transport::parseRetryAfter('99999999999'));
        self::assertNull(Transport::parseRetryAfter('soon'));
        self::assertNull(Transport::parseRetryAfter(''));
        self::assertSame(0, Transport::parseRetryAfter('Wed, 21 Oct 2015 07:28:00 GMT'));
        $inAnHour = Transport::parseRetryAfter(gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
        self::assertNotNull($inAnHour);
        self::assertEqualsWithDelta(3600, $inAnHour, 2);
    }

    public function testClientsKeepTheirOwnCredentials(): void
    {
        $first = $this->client([self::json(200, Fixtures::account())], apiKey: 'first-key');
        $second = $this->client([self::json(200, Fixtures::account())], apiKey: 'second-key');
        $first->account();
        $second->account();

        self::assertSame('first-key', $this->request(0)->getHeaderLine('X-Api-Key'));
        self::assertSame('second-key', $this->request(1)->getHeaderLine('X-Api-Key'));
    }

    public function testFailedRetryResponsesAreClosed(): void
    {
        $stream = \GuzzleHttp\Psr7\Utils::streamFor('{"title":"busy"}');
        $this->client([new Response(503, [], $stream), self::json(200, Fixtures::account())])->account();

        self::assertNull($stream->detach());
    }
}
