<?php

declare(strict_types=1);

namespace Synthient\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\Attributes\RequiresFunction;
use Synthient\Client;
use Synthient\Exception\ConnectionException;
use Synthient\Exception\TimeoutException;
use Synthient\Tests\Server\ScriptedServer;

/**
 * Tests the default Guzzle transport over real loopback connections.
 */
#[RequiresFunction('pcntl_fork')]
final class TransportTest extends TestCase
{
    /** @var list<resource> */
    private array $sockets = [];

    protected function tearDown(): void
    {
        foreach ($this->sockets as $socket) {
            fclose($socket);
        }
    }

    private static function streamHead(): string
    {
        return "HTTP/1.1 200 OK\r\nContent-Type: application/x-ndjson\r\nTransfer-Encoding: chunked\r\n\r\n";
    }

    private function realClient(ScriptedServer $server, mixed ...$options): Client
    {
        $client = new Client(...['apiKey' => self::KEY, 'baseUrl' => $server->url('/api/v4'), ...$options]);
        $this->stubSleep($client);

        return $client;
    }

    /**
     * Returns the address of a listener whose accept queue is full, so a new connection to it
     * hangs: Linux drops its SYN, and macOS connects it but never answers.
     */
    private function hangingAddress(): string
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, stream_context_create(['socket' => ['backlog' => 0]]));
        self::assertNotFalse($listener, $error);
        $this->sockets[] = $listener;
        $address = (string) stream_socket_get_name($listener, false);
        for ($i = 0; $i < 8; $i++) {
            $filler = stream_socket_client("tcp://{$address}", $errno, $error, 1, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
            self::assertNotFalse($filler, $error);
            $this->sockets[] = $filler;
        }

        return $address;
    }

    public function testFreeRequestsReuseConnections(): void
    {
        $account = ScriptedServer::json(200, Fixtures::account());
        $server = new ScriptedServer([[['read' => true], ['write' => $account], ['read' => true], ['write' => $account]]]);
        $client = $this->realClient($server);
        $client->account();
        $client->account();

        self::assertSame([0, 0], array_column($server->requests(), 'connection'));
    }

    public function testBilledLookupIsNotResentWhenAReusedConnectionDrops(): void
    {
        $server = new ScriptedServer([
            [['read' => true], ['write' => ScriptedServer::json(200, Fixtures::account())], ['read' => true], ['close' => true]],
            [['read' => true], ['close' => true]],
            [['read' => true], ['write' => ScriptedServer::json(200, Fixtures::ipInfo())]],
        ]);
        $client = $this->realClient($server);
        $client->account();
        self::thrown(ConnectionException::class, static fn() => $client->lookupIp('8.8.8.8'));

        $requests = $server->requests();
        self::assertSame(['GET /api/v4/account/me HTTP/1.1', 'GET /api/v4/lookup/ip/8.8.8.8 HTTP/1.1'], array_column($requests, 'line'));
        self::assertSame([0, 1], array_column($requests, 'connection'));
    }

    public function testBilledLookupWorksWithTheStreamHandler(): void
    {
        $server = new ScriptedServer([[['read' => true], ['write' => ScriptedServer::json(200, Fixtures::ipInfo())], ['close' => true]]]);
        $http = new GuzzleClient(['handler' => HandlerStack::create(new StreamHandler())]);

        self::assertSame('8.8.8.8', $this->realClient($server, httpClient: $http)->lookupIp('8.8.8.8')->ip);
        self::assertCount(1, $server->requests());
    }

    public function testStreamDeliversEventsBeforeTheResponseEnds(): void
    {
        $server = new ScriptedServer([[
            ['read' => true],
            ['write' => self::streamHead() . ScriptedServer::chunk("{\"ip\":\"203.0.113.7\"}\n\n")],
            ['sleep' => 1],
            ['write' => ScriptedServer::chunk("{\"ip\":\"203.0.113.8\"}\n") . "0\r\n\r\n"],
            ['close' => true],
        ]]);
        $start = hrtime(true);
        $arrivals = [];
        $events = $this->realClient($server)->proxies->stream();
        try {
            foreach ($events as $event) {
                $arrivals[] = (hrtime(true) - $start) / 1e9;
            }
        } finally {
            $events->close();
        }

        self::assertCount(2, $arrivals);
        self::assertGreaterThan(0.5, $arrivals[1] - $arrivals[0]);
        self::assertSame('application/x-ndjson', $server->requests()[0]['headers']['accept']);
    }

    public function testStalledStreamTimesOut(): void
    {
        $server = new ScriptedServer([[
            ['read' => true],
            ['write' => self::streamHead() . ScriptedServer::chunk("{\"ip\":\"203.0.113.7\"}\n")],
            ['sleep' => 5],
        ]]);
        $start = hrtime(true);
        [$events, $error] = self::drain($this->realClient($server, timeout: 0.5)->proxies->stream());

        self::assertCount(1, $events);
        self::assertInstanceOf(TimeoutException::class, $error);
        self::assertLessThan(3, (hrtime(true) - $start) / 1e9);
    }

    public function testStalledResponseTimesOut(): void
    {
        $server = new ScriptedServer([[['read' => true], ['sleep' => 5]]]);
        $start = hrtime(true);
        self::thrown(TimeoutException::class, fn() => $this->realClient($server, timeout: 0.5, maxRetries: 0)->account());

        self::assertLessThan(3, (hrtime(true) - $start) / 1e9);
    }

    public function testStreamTimesOutWhenTheResponseNeverStarts(): void
    {
        $server = new ScriptedServer([[['read' => true], ['sleep' => 5]]]);
        $start = hrtime(true);
        self::thrown(TimeoutException::class, fn() => $this->realClient($server, timeout: 0.5, maxRetries: 0)->proxies->stream());

        self::assertLessThan(3, (hrtime(true) - $start) / 1e9);
    }

    public function testStreamTimesOutWhenTheConnectionHangs(): void
    {
        $client = new Client(apiKey: self::KEY, baseUrl: 'http://' . $this->hangingAddress() . '/api/v4', timeout: 0.5, maxRetries: 0);
        $start = hrtime(true);
        self::thrown(TimeoutException::class, static fn() => $client->proxies->stream());

        self::assertLessThan(3, (hrtime(true) - $start) / 1e9);
    }

    public function testDownloadTimesOutWhenStorageHangs(): void
    {
        $storage = 'http://' . $this->hangingAddress() . '/storage/a.parquet';
        $server = new ScriptedServer([[['read' => true], ['write' => ScriptedServer::response(307, '', ['Location' => $storage])]]]);
        $start = hrtime(true);
        self::thrown(TimeoutException::class, fn() => $this->realClient($server, timeout: 0.5, maxRetries: 0)->proxies->download('2026-05-03'));

        self::assertLessThan(3, (hrtime(true) - $start) / 1e9);
    }

    public function testRefusedStreamConnectionIsNotATimeout(): void
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($listener);
        $address = (string) stream_socket_get_name($listener, false);
        fclose($listener);
        $client = new Client(apiKey: self::KEY, baseUrl: "http://{$address}/api/v4", timeout: 0.5, maxRetries: 0);

        $error = self::thrown(ConnectionException::class, static fn() => $client->proxies->stream());
        self::assertNotInstanceOf(TimeoutException::class, $error);
    }

    public function testDownloadReachesStorageWithoutTheApiKey(): void
    {
        $server = new ScriptedServer([
            [['read' => true], ['write' => ScriptedServer::response(307, '', ['Location' => '/storage/a.parquet?sig=s'])]],
            [['read' => true], ['write' => ScriptedServer::response(200, 'PAR1', ['Content-Type' => 'application/octet-stream', 'Connection' => 'close'])], ['close' => true]],
        ]);
        $body = $this->realClient($server)->proxies->download('2026-05-03');
        try {
            self::assertSame('PAR1', $body->getContents());
        } finally {
            $body->close();
        }

        [$api, $storage] = $server->requests();
        self::assertSame(self::KEY, $api['headers']['x-api-key']);
        self::assertSame('GET /storage/a.parquet?sig=s HTTP/1.1', $storage['line']);
        self::assertArrayNotHasKey('x-api-key', $storage['headers']);
        self::assertSame('synthient-php/' . Client::VERSION, $storage['headers']['user-agent']);
    }
}
