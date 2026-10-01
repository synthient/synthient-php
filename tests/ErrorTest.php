<?php

declare(strict_types=1);

namespace Synthient\Tests;

use GuzzleHttp\Psr7\Response;
use Synthient\Exception\ApiException;
use Synthient\Exception\SynthientException;
use Synthient\Internal\Transport;

final class ErrorTest extends TestCase
{
    private function apiError(Response $response): ApiException
    {
        try {
            $this->client([$response], maxRetries: 0)->lookupIp('8.8.8.8');
        } catch (ApiException $e) {
            return $e;
        }
        self::fail('expected an API error');
    }

    public function testProblemDetails(): void
    {
        $e = $this->apiError(new Response(403, ['Content-Type' => 'application/problem+json', 'X-Request-Id' => 'abc123'], json_encode([
            'type' => 'https://docs.synthient.com/errors/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'missing scope',
            'instance' => 'ignored',
        ], JSON_THROW_ON_ERROR)));

        self::assertSame(403, $e->statusCode);
        self::assertSame(403, $e->getCode());
        self::assertSame('https://docs.synthient.com/errors/forbidden', $e->type);
        self::assertSame('Forbidden', $e->title);
        self::assertSame('missing scope', $e->detail);
        self::assertSame('abc123', $e->requestId);
        self::assertSame('synthient: 403 Forbidden', $e->getMessage());
    }

    public function testValidationErrors(): void
    {
        $e = $this->apiError(new Response(422, ['Content-Type' => 'application/problem+json'], json_encode([
            'type' => 'about:blank',
            'title' => 'Unprocessable Entity',
            'status' => 422,
            'errors' => ['ip_address' => ['must be a public address']],
            'instance' => 'f00d',
        ], JSON_THROW_ON_ERROR)));

        self::assertSame(['ip_address' => ['must be a public address']], $e->errors);
        self::assertNull($e->detail);
        self::assertSame('f00d', $e->requestId);
    }

    public function testMalformedProblemFieldsAreDropped(): void
    {
        $e = $this->apiError(new Response(400, ['Content-Type' => 'application/problem+json'], '{"title":5,"detail":"bad","errors":[1,2]}'));

        self::assertNull($e->title);
        self::assertSame('bad', $e->detail);
        self::assertSame([], $e->errors);
    }

    public function testPlainTextErrors(): void
    {
        $e = $this->apiError(new Response(401, ['Content-Type' => 'text/plain'], "401 invalid api key\n"));
        self::assertSame('401 invalid api key', $e->detail);

        $e = $this->apiError(new Response(404, ['Content-Type' => 'text/plain; charset=utf-8'], "404 page not found\n"));
        self::assertSame('404 page not found', $e->detail);
    }

    public function testOtherBodiesHaveNoDetail(): void
    {
        foreach ([
            new Response(502, ['Content-Type' => 'text/html'], '<html>bad gateway</html>'),
            new Response(500),
            new Response(500, ['Content-Type' => 'text/html'], str_repeat('x', Transport::MAX_ERROR_SIZE * 2)),
        ] as $response) {
            $e = $this->apiError($response);
            self::assertSame($response->getStatusCode(), $e->statusCode);
            self::assertNull($e->detail);
            self::assertNull($e->title);
        }
    }

    public function testMessageExcludesServerDataAndSecrets(): void
    {
        $e = $this->apiError(new Response(400, ['Content-Type' => 'text/plain'], "secret-detail\r\ninjected"));

        self::assertSame('synthient: 400 Bad Request', $e->getMessage());
        self::assertStringNotContainsString(self::KEY, (string) $e);
        self::assertStringNotContainsString('secret-detail', $e->getMessage());
    }

    public function testUnknownStatusMessage(): void
    {
        self::assertSame('synthient: 599', (new ApiException(599))->getMessage());
    }

    public function testInvalidSuccessBodiesAreNotApiErrors(): void
    {
        foreach ([
            new Response(200, [], '{"ip":"8.8.8.8"} trailing'),
            new Response(200, [], '{"ip":"8.8.8.8"'),
            new Response(200, [], ''),
            new Response(200, [], '{"ip":"' . str_repeat('a', Transport::MAX_RESPONSE_SIZE) . '"}'),
        ] as $response) {
            try {
                $this->client([$response])->lookupIp('8.8.8.8');
                self::fail('expected a response error');
            } catch (SynthientException $e) {
                self::assertNotInstanceOf(ApiException::class, $e);
            }
        }
    }

    public function testOversizedSuccessBody(): void
    {
        $this->expectExceptionMessage('synthient: response body exceeds ' . Transport::MAX_RESPONSE_SIZE . ' bytes');
        $this->client([new Response(200, [], str_repeat(' ', Transport::MAX_RESPONSE_SIZE + 1))])->account();
    }
}
