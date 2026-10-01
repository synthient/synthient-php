<?php

declare(strict_types=1);

namespace Synthient\Tests;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;
use Synthient\Client;
use Synthient\Exception\ApiException;
use Synthient\Exception\ConnectionException;
use Synthient\Exception\SynthientException;
use Synthient\Internal\Transport;

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
        $client = $this->client(
            [
                self::redirect(self::SIGNED),
                self::redirect('https://cdn.storage.test/a.parquet?sig=secret-signature', 302),
                new Response(200, [], 'PAR1'),
            ],
            [
                'headers' => ['X-Default' => 'default-secret'],
                'cookies' => CookieJar::fromArray(['session' => 'cookie-secret'], 'storage.test'),
                'auth' => ['user', 'pass'],
            ],
            userAgent: 'my-app/1.2',
        );

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
            $e = self::thrown(SynthientException::class, fn() => $this->client($responses)->proxies->download('latest'));
            self::assertNotInstanceOf(ApiException::class, $e, $name);
            self::assertStringNotContainsString('secret-signature', (string) $e, $name);
            self::assertCount(\count($responses), $this->history, $name);
        }
    }

    public function testRedirectLimit(): void
    {
        $responses = [self::redirect(self::SIGNED)];
        for ($i = 0; $i <= Transport::MAX_REDIRECTS; $i++) {
            $responses[] = self::redirect("https://storage.test/{$i}?sig=secret-signature", 302);
        }
        $e = self::thrown(SynthientException::class, fn() => $this->client($responses)->proxies->download('latest'));
        self::assertStringContainsString('too many export redirects', $e->getMessage());
        self::assertCount(\count($responses), $this->history);
    }

    public function testErrors(): void
    {
        $e = self::thrown(ApiException::class, fn() => $this->client([self::problem(404, ['Retry-After' => '60'])])->proxies->download('2026-05-03'));
        self::assertSame(404, $e->statusCode);
        self::assertSame(60, $e->retryAfter);
        self::assertCount(1, $this->history);

        $e = self::thrown(SynthientException::class, fn() => $this->client([self::json(200, [])])->proxies->downloadUrl('latest'));
        self::assertNotInstanceOf(ApiException::class, $e);

        $e = self::thrown(ApiException::class, fn() => $this->client([self::redirect(self::SIGNED), new Response(403, [], '<Error>AccessDenied secret-signature</Error>')])->proxies->download('latest'));
        self::assertSame(403, $e->statusCode);
        self::assertNull($e->detail);
        self::assertStringNotContainsString('secret-signature', (string) $e);

        $this->history = [];
        $e = self::thrown(ConnectionException::class, fn() => $this->client([self::redirect(self::SIGNED), self::dropped('Could not resolve ' . self::SIGNED), new Response(200)])->proxies->download('latest'));
        self::assertNull($e->getPrevious());
        self::assertStringNotContainsString('secret-signature', (string) $e);
        self::assertCount(2, $this->history);
    }
}
