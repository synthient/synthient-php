<?php

declare(strict_types=1);

namespace Synthient\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Synthient\Client;
use Synthient\Exception\ApiException;
use Synthient\Exception\ConnectionException;
use Synthient\Exception\SynthientException;

final class DownloadTest extends TestCase
{
    private const string SIGNED = 'https://storage.test/proxies/2026-05-03.parquet?X-Signature=secret-signature';

    private static function redirect(string $location, int $status = 307): Response
    {
        return new Response($status, ['Location' => $location]);
    }

    public function testDownloadUrl(): void
    {
        $url = $this->client([self::redirect(self::SIGNED)])->proxies->downloadUrl('2026-05-03');

        self::assertSame(self::SIGNED, $url);
        self::assertSame(self::BASE_URL . '/feeds/proxies/export/2026-05-03', (string) $this->request()->getUri());
        self::assertSame(self::KEY, $this->request()->getHeaderLine('X-Api-Key'));
    }

    public function testRelativeLocationsResolveAgainstTheApi(): void
    {
        $url = $this->client([self::redirect('/files/a.parquet')])->proxies->downloadUrl('latest');

        self::assertSame('https://api.test/files/a.parquet', $url);
    }

    public function testDownloadSendsNoCredentialsToStorage(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            self::redirect(self::SIGNED),
            self::redirect('https://cdn.storage.test/a.parquet?sig=secret-signature', 302),
            new Response(200, [], 'PAR1'),
        ]));
        $stack->push(Middleware::history($this->history));
        $jar = CookieJar::fromArray(['session' => 'cookie-secret'], 'storage.test');
        $guzzle = new GuzzleClient(['handler' => $stack, 'headers' => ['X-Default' => 'default-secret'], 'cookies' => $jar, 'auth' => ['user', 'pass']]);
        $client = new Client(apiKey: self::KEY, baseUrl: self::BASE_URL, httpClient: $guzzle, userAgent: 'my-app/1.2');

        $body = $client->proxies->download('2026-05-03');
        self::assertSame('PAR1', (string) $body);
        $body->close();

        self::assertCount(3, $this->history);
        self::assertSame('default-secret', $this->request(0)->getHeaderLine('X-Default'));
        foreach ([1, 2] as $index) {
            $request = $this->request($index);
            self::assertFalse($request->hasHeader('X-Api-Key'));
            self::assertFalse($request->hasHeader('X-Default'));
            self::assertFalse($request->hasHeader('Cookie'));
            self::assertFalse($request->hasHeader('Authorization'));
            self::assertSame('synthient-php/' . Client::VERSION . ' my-app/1.2', $request->getHeaderLine('User-Agent'));
            self::assertTrue($this->history[$index]['options']['stream']);
            self::assertFalse($this->history[$index]['options']['allow_redirects']);
        }
        self::assertSame('https://cdn.storage.test/a.parquet?sig=secret-signature', (string) $this->request(2)->getUri());
    }

    public function testRejectsUnsafeRedirects(): void
    {
        $cases = [
            'https API to http' => [self::redirect('http://storage.test/a.parquet?sig=secret-signature')],
            'storage downgrade' => [self::redirect(self::SIGNED), self::redirect('http://storage.test/a?sig=secret-signature')],
            'ftp' => [self::redirect('ftp://storage.test/a?sig=secret-signature')],
            'userinfo' => [self::redirect('https://user:secret-signature@storage.test/a')],
            'no location' => [new Response(307)],
            'empty location' => [self::redirect('')],
        ];
        foreach ($cases as $name => $responses) {
            $this->history = [];
            try {
                $this->client($responses)->proxies->download('latest');
                self::fail("followed {$name}");
            } catch (SynthientException $e) {
                self::assertNotInstanceOf(ApiException::class, $e, $name);
                self::assertStringNotContainsString('secret-signature', (string) $e, $name);
            }
            self::assertCount(\count($responses), $this->history, $name);
        }
    }

    public function testRedirectLimit(): void
    {
        $responses = [self::redirect(self::SIGNED)];
        for ($i = 0; $i <= 10; $i++) {
            $responses[] = self::redirect("https://storage.test/{$i}?sig=secret-signature", 302);
        }
        try {
            $this->client($responses)->proxies->download('latest');
            self::fail('expected a redirect limit error');
        } catch (SynthientException $e) {
            self::assertStringContainsString('too many export redirects', $e->getMessage());
        }
        self::assertCount(12, $this->history);
    }

    public function testErrors(): void
    {
        try {
            $this->client([self::problem(404, ['Retry-After' => '60'])])->proxies->download('2026-05-03');
            self::fail('expected a not found error');
        } catch (ApiException $e) {
            self::assertSame(404, $e->statusCode);
            self::assertSame(60, $e->retryAfter);
        }
        self::assertCount(1, $this->history);

        try {
            $this->client([self::json(200, [])])->proxies->downloadUrl('latest');
            self::fail('expected a missing redirect error');
        } catch (SynthientException $e) {
            self::assertNotInstanceOf(ApiException::class, $e);
        }

        try {
            $this->client([self::redirect(self::SIGNED), new Response(403, [], '<Error>AccessDenied secret-signature</Error>')])->proxies->download('latest');
            self::fail('expected a storage error');
        } catch (ApiException $e) {
            self::assertSame(403, $e->statusCode);
            self::assertNull($e->detail);
            self::assertStringNotContainsString('secret-signature', (string) $e);
        }

        $this->history = [];
        try {
            $this->client([self::redirect(self::SIGNED), new ConnectException('Could not resolve ' . self::SIGNED, new Request('GET', self::SIGNED)), new Response(200)])->proxies->download('latest');
            self::fail('expected a connection error');
        } catch (ConnectionException $e) {
            self::assertNull($e->getPrevious());
            self::assertStringNotContainsString('secret-signature', (string) $e);
        }
        self::assertCount(2, $this->history);
    }
}
