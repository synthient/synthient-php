<?php

declare(strict_types=1);

namespace Synthient\Internal;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\InvalidArgumentException as GuzzleInvalidArgumentException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Random\Randomizer;
use RuntimeException;
use Synthient\Exception\ApiException;
use Synthient\Exception\ConnectionException;
use Synthient\Exception\SynthientException;
use Synthient\Exception\TimeoutException;
use Synthient\RateLimit;
use Synthient\ResponseMetadata;

/**
 * Sends API requests with authentication, retries, and response checks.
 *
 * @internal
 */
final class Transport
{
    public const int MAX_RESPONSE_SIZE = 16 << 20;
    public const int MAX_ERROR_SIZE = 64 << 10;
    public const int MAX_REDIRECTS = 10;
    private const int MAX_BACKOFF = 30;
    private const int MAX_RETRY_AFTER = 60;
    private const int MAX_RETRY_AFTER_HEADER = 2 ** 31;
    private const string TLS_FAILURE = '/cURL error (35|51|53|54|58|59|60|64|66|77|80|82|83|90|91|98):|certificate verify failed/i';
    private const string STREAM_HANDLER_REJECTS_CURL = '"curl" request option to the stream handler is not supported';

    private readonly ClientInterface $http;
    private readonly bool $defaultHttp;

    /** @var array<int|string, mixed> */
    private readonly array $curlDefaults;

    /** @var Closure(float): void */
    private Closure $sleep;

    /**
     * @param (Closure(ResponseMetadata): void)|null $responseHook
     */
    public function __construct(
        ?ClientInterface $http,
        #[\SensitiveParameter]
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int $maxRetries,
        private readonly float $connectTimeout,
        private readonly float $timeout,
        private readonly string $userAgent,
        private readonly ?Closure $responseHook,
    ) {
        $this->http = $http ?? new GuzzleClient();
        $this->defaultHttp = $http === null;
        $curl = $http instanceof GuzzleClient ? $http->getConfig('curl') : null;
        $this->curlDefaults = \is_array($curl) ? $curl : [];
        $this->sleep = static function (float $seconds): void {
            $remaining = time_nanosleep((int) $seconds, (int) (fmod($seconds, 1) * 1e9));
            while (\is_array($remaining)) {
                $remaining = time_nanosleep($remaining['seconds'], $remaining['nanoseconds']);
            }
        };
    }

    /**
     * Sends a JSON request and decodes a 2xx response into $class, or discards it when
     * $class is null.
     *
     * @template T of object
     *
     * @param class-string<T>|null $class
     * @param array<string, int|string> $query
     * @param array<string, mixed>|null $body
     *
     * @return ($class is null ? null : T)
     */
    public function request(
        ?string $class,
        string $method,
        string $path,
        RetryPolicy $policy,
        array $query = [],
        ?array $body = null,
    ): ?object {
        $response = $this->send($method, $path, $policy, false, $query, $body);
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw $this->apiError($response);
        }
        if ($class === null) {
            $response->getBody()->close();

            return null;
        }
        $json = self::read($response->getBody(), self::MAX_RESPONSE_SIZE);
        if (\strlen($json) > self::MAX_RESPONSE_SIZE) {
            throw new SynthientException('synthient: response body exceeds ' . self::MAX_RESPONSE_SIZE . ' bytes');
        }

        return Decoder::decode($class, $json);
    }

    /**
     * Connects to an NDJSON stream and returns its unread body. The caller closes it.
     */
    public function openStream(string $path): StreamInterface
    {
        $this->requireStreaming();
        $response = $this->send('GET', $path, RetryPolicy::Transient, true);
        if ($response->getStatusCode() !== 200) {
            throw $this->apiError($response);
        }

        return new Body($response->getBody());
    }

    /**
     * Asks the API where an export is stored and returns the presigned URL.
     */
    public function downloadUrl(string $path): string
    {
        $response = $this->send('GET', $path, RetryPolicy::Transient, false);
        $status = $response->getStatusCode();
        if ($status >= 400) {
            throw $this->apiError($response);
        }
        $response->getBody()->close();
        if ($status < 300) {
            throw new SynthientException("synthient: expected a redirect to the export, got status {$status}");
        }

        return self::redirectTarget($this->baseUrl . $path, $response);
    }

    /**
     * Asks the API where an export is stored, then downloads it from storage without the API
     * key or the client's default headers, cookies, and auth, following up to ten validated
     * redirects. Nothing is retried, and errors never include the presigned URL.
     */
    public function openDownload(string $path): StreamInterface
    {
        $this->requireStreaming();
        $url = $this->downloadUrl($path);
        for ($hop = 0; ; $hop++) {
            $start = hrtime(true);
            try {
                $response = $this->http->send(new Request('GET', $url, ['User-Agent' => $this->userAgent]), [
                    ...$this->options(true),
                    'headers' => null,
                    'cookies' => false,
                    'auth' => null,
                ]);
            } catch (GuzzleException $e) {
                throw $this->transportError($e, 'export download', false, $start);
            }
            $status = $response->getStatusCode();
            if ($status === 200) {
                return new Body($response->getBody());
            }
            $response->getBody()->close();
            if ($status < 300 || $status >= 400 || !$response->hasHeader('Location')) {
                throw new ApiException($status);
            }
            if ($hop === self::MAX_REDIRECTS) {
                throw new SynthientException('synthient: too many export redirects');
            }
            $url = self::redirectTarget($url, $response);
        }
    }

    public static function backoff(int $attempt): float
    {
        return min(2 ** min($attempt, 5), self::MAX_BACKOFF) * (new Randomizer())->getFloat(0.75, 1.25);
    }

    /**
     * Parses Retry-After as seconds or an HTTP date. Negative and past values become 0,
     * very large values are clamped, and anything else is null.
     */
    public static function parseRetryAfter(string $value): ?int
    {
        $value = trim($value);
        if (preg_match('/^-?\d+$/', $value) === 1) {
            return (int) min(max((float) $value, 0), self::MAX_RETRY_AFTER_HEADER);
        }
        $when = DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $value, new DateTimeZone('UTC'));
        if ($when === false) {
            return null;
        }

        return max((int) ceil($when->getTimestamp() - microtime(true)), 0);
    }

    public static function parseRateLimit(ResponseInterface $response): ?RateLimit
    {
        $values = [];
        foreach (['RateLimit-Limit', 'RateLimit-Remaining', 'RateLimit-Reset'] as $name) {
            $value = trim($response->getHeaderLine($name));
            if (preg_match('/^-?\d{1,10}$/', $value) !== 1) {
                return null;
            }
            $values[] = (int) $value;
        }

        return new RateLimit($values[0], $values[1], max($values[2], 0));
    }

    /**
     * @param array<string, int|string> $query
     * @param array<string, mixed>|null $body
     */
    private function send(
        string $method,
        string $path,
        RetryPolicy $policy,
        bool $stream,
        array $query = [],
        ?array $body = null,
    ): ResponseInterface {
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        $headers = [
            'X-Api-Key' => $this->apiKey,
            'User-Agent' => $this->userAgent,
            'Accept' => $stream ? 'application/x-ndjson' : 'application/json',
        ];
        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }
        $options = $this->options($stream);
        if ($policy === RetryPolicy::Billed && \defined('CURLOPT_FRESH_CONNECT')) {
            // libcurl silently resends a request whose reused connection dies before the
            // response starts, which could charge a lookup twice. A fresh connection is
            // never resent. A request's curl option replaces the client's, so keep those.
            $options['curl'] = [CURLOPT_FRESH_CONNECT => true] + $this->curlDefaults;
        }

        for ($attempt = 0; ; $attempt++) {
            $canRetry = $attempt < $this->maxRetries;
            $start = hrtime(true);
            try {
                $response = $this->http->send(new Request($method, $url, $headers, $payload), $options);
            } catch (GuzzleException $e) {
                if ($e instanceof GuzzleInvalidArgumentException) {
                    // Guzzle 8's stream handler rejects cURL options before sending anything,
                    // and it never reuses a connection, so the lookup can go without them. Any
                    // other rejection, such as from a cURL handler that requires persistent
                    // connection sharing, must not drop the fresh connection.
                    if (isset($options['curl']) && str_contains($e->getMessage(), self::STREAM_HANDLER_REJECTS_CURL)) {
                        unset($options['curl']);
                        $attempt--;

                        continue;
                    }

                    throw new InvalidArgumentException('synthient: the httpClient rejected the request: ' . $e->getMessage());
                }
                $transient = $e instanceof TransferException && preg_match(self::TLS_FAILURE, $e->getMessage()) !== 1;
                if ($canRetry && $policy === RetryPolicy::Transient && $transient) {
                    ($this->sleep)(self::backoff($attempt));

                    continue;
                }

                throw $this->transportError($e, 'request', true, $start);
            }
            try {
                if ($this->responseHook !== null) {
                    ($this->responseHook)(new ResponseMetadata(
                        $response->getStatusCode(),
                        $response->getHeaderLine('X-Request-Id') ?: null,
                        self::parseRateLimit($response),
                        $response->getHeaders(),
                    ));
                }
            } catch (\Throwable $e) {
                $response->getBody()->close();

                throw $e;
            }
            $delay = $canRetry ? self::retryDelay($policy, $response, $attempt) : null;
            if ($delay === null) {
                return $response;
            }
            $response->getBody()->close();
            ($this->sleep)($delay);
        }
    }

    /**
     * Returns how long to wait before retrying, or null to stop. A 429 without Retry-After
     * is not retried, because the rate limiter always sends one: its absence means the key's
     * monthly budget is spent or too many of its streams are open. A server that asks for
     * more than a minute gets no retry, so the caller sees the error and its retryAfter.
     */
    private static function retryDelay(RetryPolicy $policy, ResponseInterface $response, int $attempt): ?float
    {
        $status = $response->getStatusCode();
        if ($policy !== RetryPolicy::Transient && $status !== 429) {
            return null;
        }
        if (!\in_array($status, [429, 500, 502, 503, 504], true)) {
            return null;
        }
        $retryAfter = self::parseRetryAfter($response->getHeaderLine('Retry-After'));
        if ($status === 429 && $retryAfter === null) {
            return null;
        }
        if ($retryAfter > self::MAX_RETRY_AFTER) {
            return null;
        }

        return max(self::backoff($attempt), (float) $retryAfter);
    }

    /**
     * @return array{allow_redirects: false, http_errors: false, stream: bool, connect_timeout: float, timeout: float, read_timeout: float}
     */
    private function options(bool $stream): array
    {
        return [
            'allow_redirects' => false,
            'http_errors' => false,
            'stream' => $stream,
            'connect_timeout' => $this->connectTimeout,
            'timeout' => $this->timeout,
            'read_timeout' => $this->timeout,
        ];
    }

    /**
     * Reads at most $limit + 1 bytes, so a caller can tell an oversized body apart.
     */
    private static function read(StreamInterface $body, int $limit): string
    {
        $body = new Body($body);
        $data = '';
        try {
            while (!$body->eof() && \strlen($data) <= $limit) {
                $data .= $body->read(min(65536, $limit + 1 - \strlen($data)));
            }
        } finally {
            $body->close();
        }

        return $data;
    }

    /**
     * Guzzle's exception is not chained, because its request holds the API key. Its message
     * is kept as the cause unless it could hold a presigned URL.
     *
     * Guzzle's stream handler does not always say that it gave up at the timeout. Guzzle 7
     * reports any connection that failed as refused, and both majors report a response that
     * never started as a failed request. A failure that took the whole timeout counts as a
     * timeout, while a refused connection fails at once.
     */
    private function transportError(
        #[\SensitiveParameter]
        GuzzleException $e,
        string $action,
        bool $keepMessage,
        int $start,
    ): ConnectionException {
        $previous = $keepMessage ? new RuntimeException($e->getMessage()) : null;
        $timedOut = $e instanceof ConnectTimeoutException
            || $e instanceof NetworkTimeoutException
            || $e instanceof ResponseTimeoutException
            || preg_match('/cURL error 28:|timed out/i', $e->getMessage()) === 1
            || (hrtime(true) - $start) / 1e9 >= $this->timeout * 0.95;

        return $timedOut
            ? new TimeoutException("synthient: {$action} timed out", 0, $previous)
            : new ConnectionException("synthient: {$action} failed", 0, $previous);
    }

    private function apiError(ResponseInterface $response): ApiException
    {
        $status = $response->getStatusCode();
        $requestId = $response->getHeaderLine('X-Request-Id') ?: null;
        $retryAfter = self::parseRetryAfter($response->getHeaderLine('Retry-After'));
        $headers = $response->getHeaders();
        try {
            $body = substr(self::read($response->getBody(), self::MAX_ERROR_SIZE), 0, self::MAX_ERROR_SIZE);
        } catch (SynthientException $e) {
            return new ApiException($status, requestId: $requestId, retryAfter: $retryAfter, headers: $headers, previous: $e);
        }

        $problem = json_decode($body, true);
        if (!\is_array($problem)) {
            $plain = str_starts_with(strtolower($response->getHeaderLine('Content-Type')), 'text/plain');
            $detail = $plain ? trim($body) : null;

            return new ApiException($status, detail: $detail, requestId: $requestId, retryAfter: $retryAfter, headers: $headers);
        }
        $errors = [];
        foreach (\is_array($problem['errors'] ?? null) ? $problem['errors'] : [] as $field => $messages) {
            $strings = \is_array($messages) && array_is_list($messages) && array_filter($messages, is_string(...)) === $messages;
            if ($strings) {
                $errors[(string) $field] = $messages;
            }
        }

        return new ApiException(
            $status,
            type: \is_string($problem['type'] ?? null) ? $problem['type'] : null,
            title: \is_string($problem['title'] ?? null) ? $problem['title'] : null,
            detail: \is_string($problem['detail'] ?? null) ? $problem['detail'] : null,
            errors: $errors,
            requestId: $requestId ?? (\is_string($problem['instance'] ?? null) ? $problem['instance'] : null),
            retryAfter: $retryAfter,
            headers: $headers,
        );
    }

    /**
     * Resolves a redirect's Location. It must be an absolute http or https URL with a host
     * and no credentials, and an https request never redirects to http. Errors never include
     * the URL, which is presigned.
     */
    private static function redirectTarget(
        #[\SensitiveParameter]
        string $from,
        #[\SensitiveParameter]
        ResponseInterface $response,
    ): string {
        $location = trim($response->getHeaderLine('Location'));
        try {
            $base = new Uri($from);
            $target = UriResolver::resolve($base, new Uri($location));
        } catch (InvalidArgumentException) {
            throw new SynthientException('synthient: export redirect has no valid location');
        }
        if ($location === '' || $target->getHost() === '' || $target->getUserInfo() !== '') {
            throw new SynthientException('synthient: export redirect has no valid location');
        }
        if (!\in_array($target->getScheme(), ['http', 'https'], true)) {
            throw new SynthientException('synthient: export redirect has an unsupported scheme');
        }
        if ($base->getScheme() === 'https' && $target->getScheme() !== 'https') {
            throw new SynthientException('synthient: export redirect does not use https');
        }

        return (string) $target;
    }

    /**
     * Guzzle streams only through its PHP stream handler. Without allow_url_fopen the
     * default client would buffer an endless stream forever, so fail clearly instead.
     */
    private function requireStreaming(): void
    {
        if ($this->defaultHttp && !filter_var(\ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
            throw new SynthientException(
                'synthient: streams and downloads need the allow_url_fopen ini setting, '
                . 'or an httpClient whose handler streams responses',
            );
        }
    }
}
