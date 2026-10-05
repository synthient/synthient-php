<?php

declare(strict_types=1);

namespace Synthient\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Synthient\Client;

final class ClientTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('SYNTHIENT_API_KEY');
    }

    public function testRequiresAnApiKey(): void
    {
        putenv('SYNTHIENT_API_KEY');
        foreach ([null, '', '   '] as $key) {
            $e = self::thrown(InvalidArgumentException::class, static fn() => new Client(apiKey: $key));
            self::assertStringContainsString('missing API key', $e->getMessage());
        }
    }

    public function testReadsTheKeyFromTheEnvironment(): void
    {
        putenv('SYNTHIENT_API_KEY= env-key ');
        $client = $this->client([self::json(200, Fixtures::account())], apiKey: null);
        $client->account();

        self::assertSame('env-key', $this->request()->getHeaderLine('X-Api-Key'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'ftp scheme' => [['baseUrl' => 'ftp://api.test/api/v4']];
        yield 'missing host' => [['baseUrl' => 'https://']];
        yield 'query' => [['baseUrl' => 'https://api.test/api/v4?debug=1']];
        yield 'fragment' => [['baseUrl' => 'https://api.test/api/v4#x']];
        yield 'credentials' => [['baseUrl' => 'https://user:pass@api.test/api/v4']];
        yield 'negative retries' => [['maxRetries' => -1]];
        yield 'zero timeout' => [['timeout' => 0.0]];
        yield 'infinite connect timeout' => [['connectTimeout' => INF]];
        yield 'user agent newline' => [['userAgent' => "my-app\r\nX-Evil: 1"]];
        yield 'blank user agent' => [['userAgent' => ' ']];
        yield 'key with a newline' => [['apiKey' => "key\nX-Evil: 1"]];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('invalidOptions')]
    public function testRejectsInvalidOptions(array $options): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Client(...['apiKey' => self::KEY, ...$options]);
    }

    public function testKeepsTheApiKeyOutOfStackTraces(): void
    {
        $ignoreArgs = (string) \ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        try {
            $e = self::thrown(InvalidArgumentException::class, static fn() => new Client(apiKey: 'secret-key', baseUrl: 'ftp://x'));
        } finally {
            ini_set('zend.exception_ignore_args', $ignoreArgs);
        }

        self::assertStringContainsString('ftp://x', $e->getTraceAsString());
        self::assertStringNotContainsString('secret-key', $e->getTraceAsString());
    }

    public function testSendsAuthenticationAndIdentification(): void
    {
        $client = $this->client([self::json(200, Fixtures::account())], userAgent: 'my-app/1.2', baseUrl: self::BASE_URL . '/');
        $client->account();

        $request = $this->request();
        self::assertSame('GET', $request->getMethod());
        self::assertSame(self::BASE_URL . '/account/me', (string) $request->getUri());
        self::assertSame(self::KEY, $request->getHeaderLine('X-Api-Key'));
        self::assertSame('synthient-php/' . Client::VERSION . ' my-app/1.2', $request->getHeaderLine('User-Agent'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertFalse($request->hasHeader('Content-Type'));
    }

    public function testDisablesRedirectsAndHttpErrorsOnEveryRequest(): void
    {
        $client = $this->client([self::json(200, Fixtures::account())], timeout: 5.0, connectTimeout: 2.0);
        $client->account();

        $options = $this->history[0]['options'];
        self::assertFalse($options['allow_redirects']);
        self::assertFalse($options['http_errors']);
        self::assertFalse($options['stream']);
        self::assertSame(2.0, $options['connect_timeout']);
        self::assertSame(5.0, $options['timeout']);
    }
}
