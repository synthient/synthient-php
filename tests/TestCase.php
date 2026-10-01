<?php

declare(strict_types=1);

namespace Synthient\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Synthient\Client;
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
     * Builds a client whose requests are answered by $responses in order and recorded in
     * $this->history. Retries record their delay in $this->sleeps instead of sleeping.
     *
     * @param list<ResponseInterface|Throwable> $responses
     */
    protected function client(array $responses, mixed ...$options): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $client = new Client(...[
            'apiKey' => self::KEY,
            'baseUrl' => self::BASE_URL,
            'httpClient' => new GuzzleClient(['handler' => $stack]),
            ...$options,
        ]);
        $this->stubSleep($client);

        return $client;
    }

    protected function stubSleep(Client $client): void
    {
        $sleeps = &$this->sleeps;
        $transport = (fn(): object => $this->transport)->call($client);
        (function () use (&$sleeps): void {
            $this->sleep = static function (float $seconds) use (&$sleeps): void {
                $sleeps[] = $seconds;
            };
        })->call($transport);
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
        return new Response($status, ['Content-Type' => 'application/problem+json', ...$headers], json_encode([
            'type' => 'about:blank',
            'title' => 'Problem',
            'status' => $status,
            'detail' => 'something went wrong',
        ], JSON_THROW_ON_ERROR));
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
