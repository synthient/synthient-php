<?php

declare(strict_types=1);

namespace Synthient\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionProperty;
use Synthient\Client;
use Synthient\Feed;
use Synthient\Internal\Transport;
use Throwable;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected const string KEY = 'test-key';
    protected const string BASE_URL = 'https://api.test/api/v4';

    /** @var array<array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<mixed>}> */
    protected array $history = [];

    /** @var list<float> */
    protected array $sleeps = [];

    /**
     * Builds a client whose requests are answered by $handler, either a list of responses in
     * order or a Guzzle handler, and recorded in $this->history. Retries record their delay
     * in $this->sleeps instead of sleeping.
     *
     * @param list<ResponseInterface|Throwable>|callable $handler
     * @param array<string, mixed> $guzzle more Guzzle client config, such as default headers
     */
    protected function client(array|callable $handler, array $guzzle = [], mixed ...$options): Client
    {
        $stack = HandlerStack::create(\is_array($handler) ? new MockHandler($handler) : $handler);
        $stack->push(Middleware::history($this->history));
        $client = new Client(...[
            'apiKey' => self::KEY,
            'baseUrl' => self::BASE_URL,
            'httpClient' => new GuzzleClient(['handler' => $stack, ...$guzzle]),
            ...$options,
        ]);
        $this->stubSleep($client);

        return $client;
    }

    protected function stubSleep(Client $client): void
    {
        $transport = (new ReflectionProperty(Client::class, 'transport'))->getValue($client);
        (new ReflectionProperty(Transport::class, 'sleep'))->setValue($transport, function (float $seconds): void {
            $this->sleeps[] = $seconds;
        });
    }

    /**
     * @return array<string, Feed<covariant object>> each feed by its path under /feeds
     */
    protected static function feeds(Client $client): array
    {
        return [
            'proxies' => $client->proxies,
            'anonymizers' => $client->anonymizers,
            'torrents' => $client->torrents,
            'ja4t' => $client->ja4t,
            'helio/http' => $client->heliosHttp,
            'helio/https' => $client->heliosHttps,
            'helio/dns' => $client->heliosDns,
            'helio/adb' => $client->heliosAdb,
        ];
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    protected static function json(int $status, mixed $body, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json', ...$headers], json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    protected static function problem(int $status, array $headers = []): Response
    {
        return self::json($status, [
            'type' => 'about:blank',
            'title' => 'Problem',
            'status' => $status,
            'detail' => 'something went wrong',
        ], ['Content-Type' => 'application/problem+json', ...$headers]);
    }

    protected static function dropped(string $message = 'cURL error 56: Connection reset by peer'): ConnectException
    {
        return new ConnectException($message, new Request('GET', 'https://api.test'));
    }

    /**
     * Runs $fn with trace arguments recorded, as a development ini does, and returns every
     * argument in the traces of the $class exception it throws and of its causes.
     *
     * @param class-string<Throwable> $class
     *
     * @return list<mixed>
     */
    protected static function traceArguments(string $class, callable $fn): array
    {
        $ignoreArgs = (string) \ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        try {
            $e = self::thrown($class, $fn);
        } finally {
            ini_set('zend.exception_ignore_args', $ignoreArgs);
        }
        $args = [];
        for (; $e !== null; $e = $e->getPrevious()) {
            foreach ($e->getTrace() as $frame) {
                array_push($args, ...$frame['args'] ?? []);
            }
        }

        return $args;
    }

    /**
     * Runs $fn and returns the $class exception it throws. Other exceptions propagate.
     *
     * @template E of Throwable
     *
     * @param class-string<E> $class
     *
     * @return E
     */
    protected static function thrown(string $class, callable $fn): Throwable
    {
        try {
            $fn();
        } catch (Throwable $e) {
            if (!$e instanceof $class) {
                throw $e;
            }

            return $e;
        }
        self::fail("expected {$class}");
    }

    /**
     * Iterates until the end or the first exception, returning what was yielded and the
     * exception.
     *
     * @template T
     *
     * @param iterable<T> $items
     *
     * @return array{list<T>, Throwable|null}
     */
    protected static function drain(iterable $items): array
    {
        $seen = [];
        try {
            foreach ($items as $item) {
                $seen[] = $item;
            }
        } catch (Throwable $e) {
            return [$seen, $e];
        }

        return [$seen, null];
    }

    protected function request(int $index = 0): RequestInterface
    {
        self::assertArrayHasKey($index, $this->history, "request {$index} was not sent");

        return $this->history[$index]['request'];
    }
}
