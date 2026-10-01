<?php

declare(strict_types=1);

namespace Synthient\Tests;

use GuzzleHttp\Psr7\PumpStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use LogicException;
use Psr\Http\Message\StreamInterface;
use Synthient\Enum\ProxyType;
use Synthient\EventStream;
use Synthient\Exception\ApiException;
use Synthient\Exception\SynthientException;

final class StreamTest extends TestCase
{
    /**
     * A body that returns at most $size bytes per read, to split lines and UTF-8 sequences
     * across chunks.
     */
    private static function chunked(string $data, int $size): StreamInterface
    {
        $offset = 0;

        return new PumpStream(static function () use ($data, $size, &$offset): string|false {
            if ($offset >= \strlen($data)) {
                return false;
            }
            $chunk = substr($data, $offset, $size);
            $offset += $size;

            return $chunk;
        });
    }

    private static function ndjson(StreamInterface|string $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/x-ndjson'], $body);
    }

    public function testStream(): void
    {
        $body = "{\"ip\":\"203.0.113.7\",\"provider\":\"acme\",\"type\":\"RESIDENTIAL_PROXY\",\"country_code\":\"US\",\"asn\":4294967295,\"timestamp\":1760000000}\n"
            . "\n   \n"
            . "{\"ip\":\"203.0.113.8\",\"future\":1}\r\n";
        $client = $this->client([self::ndjson($body)]);
        $events = iterator_to_array($client->proxies->stream());

        self::assertSame(self::BASE_URL . '/feeds/proxies/stream', (string) $this->request()->getUri());
        self::assertSame('application/x-ndjson', $this->request()->getHeaderLine('Accept'));
        self::assertTrue($this->history[0]['options']['stream']);
        self::assertCount(2, $events);
        self::assertSame(ProxyType::RESIDENTIAL_PROXY, $events[0]->type);
        self::assertSame(4_294_967_295, $events[0]->asn);
        self::assertSame(1_760_000_000, $events[0]->timestamp?->getTimestamp());
        self::assertSame('203.0.113.8', $events[1]->ip);
        self::assertNull($events[1]->provider);
    }

    public function testEventsSplitAcrossReads(): void
    {
        $lines = "{\"name\":\"caf\u{e9} \u{1f600}\",\"files\":[{\"path\":\"a\",\"length\":1}]}\n{\"name\":\"two\"}\n{\"name\":\"last without newline\"}";
        foreach ([1, 2, 3, 7, 64] as $size) {
            $events = iterator_to_array($this->client([self::ndjson(self::chunked($lines, $size))])->torrents->stream());

            self::assertSame(["caf\u{e9} \u{1f600}", 'two', 'last without newline'], array_map(static fn($event) => $event->name, $events), "chunk size {$size}");
            self::assertSame(1, $events[0]->files[0]->length ?? null);
        }
    }

    public function testHeliosEvents(): void
    {
        $https = $this->client([self::ndjson(
            '{"timestamp":1760000000123,"tunnel_id":9,"domain":"example.com","port":443,"protocol":"https","meta":{"proxy_ip":"203.0.113.9"},"details":{"sni":"example.com","cipher_suites":[{"code":4865,"name":"TLS_AES_128_GCM_SHA256"}],"has_grease":true},"raw":null}' . "\n"
            . '{"timestamp":1760000000123,"tunnel_id":10,"domain":"example.com","port":443,"protocol":"https","meta":{},"details":null}' . "\n",
        )])->heliosHttps->stream();
        $events = iterator_to_array($https);

        self::assertSame('2025-10-09T08:53:20.123+00:00', $events[0]->timestamp->format('Y-m-d\TH:i:s.vP'));
        self::assertSame('203.0.113.9', $events[0]->meta->proxyIp);
        self::assertNotNull($events[0]->details);
        self::assertSame('example.com', $events[0]->details->sni);
        self::assertSame(4865, $events[0]->details->cipherSuites[0]->code ?? null);
        self::assertTrue($events[0]->details->hasGrease);
        self::assertNull($events[0]->raw);
        self::assertNull($events[1]->details);

        $http = iterator_to_array($this->client([self::ndjson(
            '{"timestamp":1760000000123,"tunnel_id":9,"domain":"example.com","port":80,"protocol":"http","meta":{},"details":{"method":"GET","uri":"/","version":"HTTP/1.1","headers":{"Host":"example.com"}},"raw":"R0VUIC8="}' . "\n",
        )])->heliosHttp->stream());
        self::assertSame(['Host' => 'example.com'], $http[0]->details?->headers);
        self::assertSame('GET /', base64_decode((string) $http[0]->raw, true));

        $adb = iterator_to_array($this->client([self::ndjson(
            '{"session":"' . str_repeat('a', 32) . '","sequential_id":1,"command":"id","hash":"' . str_repeat('b', 64) . '"}',
        )])->heliosAdb->stream());
        self::assertSame('id', $adb[0]->command);
        self::assertSame(1, $adb[0]->sequentialId);
    }

    public function testConnectErrorsAreThrownBeforeIteration(): void
    {
        foreach ([self::problem(403), self::problem(429), new Response(204)] as $response) {
            try {
                $this->client([$response])->proxies->stream();
                self::fail('expected an API error');
            } catch (ApiException $e) {
                self::assertSame($response->getStatusCode(), $e->statusCode);
            }
        }
    }

    public function testConnectIsRetried(): void
    {
        $events = iterator_to_array($this->client([self::problem(503), self::ndjson("{\"ip\":\"203.0.113.7\"}\n")])->proxies->stream());

        self::assertCount(2, $this->history);
        self::assertCount(1, $events);
    }

    public function testInvalidEventEndsTheStream(): void
    {
        [$events, $error] = self::drain($this->client([self::ndjson("{\"ip\":\"203.0.113.7\"}\n{\"ip\":7}\n{\"ip\":\"203.0.113.8\"}\n")])->proxies->stream());

        self::assertCount(1, $events);
        self::assertInstanceOf(SynthientException::class, $error);
        self::assertStringContainsString('ip must be a string', $error->getMessage());

        $this->expectException(SynthientException::class);
        iterator_to_array($this->client([self::ndjson("{\"ip\":\"203.0.113.7\"}\n{\"ip\":")])->proxies->stream());
    }

    public function testOversizedEvent(): void
    {
        $remaining = EventStream::MAX_EVENT_SIZE + 1;
        $body = new PumpStream(static function () use (&$remaining): string|false {
            if ($remaining <= 0) {
                return false;
            }
            $chunk = str_repeat('a', min(1 << 20, $remaining));
            $remaining -= \strlen($chunk);

            return $chunk;
        });

        $this->expectExceptionMessage('stream event exceeds');
        iterator_to_array($this->client([self::ndjson($body)])->proxies->stream());
    }

    public function testStreamsAreSingleUseAndClosed(): void
    {
        $body = Utils::streamFor("{\"ip\":\"203.0.113.7\"}\n{\"ip\":\"203.0.113.8\"}\n");
        $events = $this->client([self::ndjson($body)])->proxies->stream();
        try {
            foreach ($events as $event) {
                break;
            }
        } finally {
            $events->close();
        }
        self::assertNull($body->detach());

        $this->expectException(LogicException::class);
        iterator_to_array($events);
    }
}
