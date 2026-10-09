<?php

declare(strict_types=1);

namespace Synthient;

use Closure;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use Synthient\Event\AnonymizerEvent;
use Synthient\Event\HeliosAdbEvent;
use Synthient\Event\HeliosDnsEvent;
use Synthient\Event\HeliosHttpEvent;
use Synthient\Event\HeliosHttpsEvent;
use Synthient\Event\Ja4tEvent;
use Synthient\Event\ProxyEvent;
use Synthient\Event\TorrentEvent;
use Synthient\Exception\ApiException;
use Synthient\Internal\IpBatch;
use Synthient\Internal\RetryPolicy;
use Synthient\Internal\Transport;
use Synthient\Model\Account;
use Synthient\Model\DomainInfo;
use Synthient\Model\IpInfo;

/**
 * A client for the Synthient API.
 *
 * Create one client and reuse it. Constructing it makes no requests.
 *
 * Requests that cost no credits are retried on 429, 500, 502, 503, and 504 responses and on
 * transport failures, except health(), which retries only 429. Billed lookups are retried
 * only on 429, which the API returns before doing any work, and are never resent on a
 * reused connection. Retries back off exponentially with jitter and honor Retry-After up to
 * one minute. A 429 without Retry-After is not retried, because it means the key's monthly
 * request budget is spent or too many of its streams are open.
 *
 * Errors throw ApiException for an error status, ConnectionException or TimeoutException for
 * a transport failure, and SynthientException for a malformed response. Invalid arguments
 * throw InvalidArgumentException before any request is made.
 *
 * A Guzzle client passed as httpClient keeps its defaults, such as proxy, verify, and cert,
 * except that the SDK sets allow_redirects, http_errors, stream, and timeouts on every
 * request and adds CURLOPT_FRESH_CONNECT to its curl option on lookups. Lookups through
 * Guzzle's stream handler, which never reuses connections, go without it, and lookups
 * through a client that requires persistent cURL sharing throw InvalidArgumentException.
 * Export downloads drop its default headers, cookies, and auth. It must not retry requests
 * or set a default query, and streams and downloads need a handler that streams response
 * bodies, such as Guzzle's default.
 */
final class Client
{
    public const string VERSION = '1.0.0';
    public const string DEFAULT_BASE_URL = 'https://api.synthient.com/api/v4';
    public const int MAX_BATCH_SIZE = 1000;

    /** @var Feed<ProxyEvent> residential, mobile, and datacenter proxies */
    public readonly Feed $proxies;

    /** @var Feed<AnonymizerEvent> VPN and anonymizer IP ranges */
    public readonly Feed $anonymizers;

    /** @var Feed<TorrentEvent> torrents and the peers sharing them */
    public readonly Feed $torrents;

    /** @var Feed<Ja4tEvent> JA4T TCP fingerprints */
    public readonly Feed $ja4t;

    /** @var Feed<HeliosHttpEvent> HTTP requests captured by Helios honeypots */
    public readonly Feed $heliosHttp;

    /** @var Feed<HeliosHttpsEvent> TLS handshakes captured by Helios honeypots */
    public readonly Feed $heliosHttps;

    /** @var Feed<HeliosDnsEvent> tunnel destinations captured by Helios honeypots */
    public readonly Feed $heliosDns;

    /** @var Feed<HeliosAdbEvent> Android Debug Bridge commands captured by Helios honeypots */
    public readonly Feed $heliosAdb;

    private readonly Transport $transport;

    /**
     * @param string|null $apiKey the API key; defaults to the SYNTHIENT_API_KEY environment variable
     * @param string $baseUrl the API base URL, including the version path
     * @param int $maxRetries how many times a failed request is retried; 0 disables retries
     * @param float $connectTimeout seconds to wait for a connection
     * @param float $timeout seconds to wait for a whole response, or for the next data on a
     *                       stream or download; the API sends a stream keepalive every 15 seconds
     * @param ClientInterface|null $httpClient a Guzzle client, for proxies, TLS settings, or a custom handler
     * @param string|null $userAgent appended to the User-Agent header, such as "my-app/1.2"
     * @param (Closure(ResponseMetadata): void)|null $responseHook called with every API response
     *
     * @throws InvalidArgumentException when the API key is missing or an option is invalid
     */
    public function __construct(
        #[\SensitiveParameter]
        ?string $apiKey = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $maxRetries = 2,
        float $connectTimeout = 10.0,
        float $timeout = 60.0,
        ?ClientInterface $httpClient = null,
        ?string $userAgent = null,
        ?Closure $responseHook = null,
    ) {
        $apiKey = trim($apiKey ?? (string) getenv('SYNTHIENT_API_KEY'));
        if ($apiKey === '') {
            throw new InvalidArgumentException('missing API key: pass apiKey or set SYNTHIENT_API_KEY');
        }
        if (preg_match('/^[\x21-\x7e]+$/', $apiKey) !== 1) {
            throw new InvalidArgumentException('the API key contains invalid characters');
        }
        try {
            $uri = new Uri($baseUrl);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('invalid base URL');
        }
        $extras = $uri->getQuery() . $uri->getFragment() . $uri->getUserInfo();
        if (!\in_array($uri->getScheme(), ['http', 'https'], true) || $uri->getHost() === '' || $extras !== '') {
            throw new InvalidArgumentException('invalid base URL: use http or https with a host and no query, fragment, or credentials');
        }
        if ($maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries must not be negative');
        }
        foreach (['connectTimeout' => $connectTimeout, 'timeout' => $timeout] as $name => $seconds) {
            if (!is_finite($seconds) || $seconds < 0.001) {
                throw new InvalidArgumentException("{$name} must be at least 0.001 seconds");
            }
        }
        $agent = 'synthient-php/' . self::VERSION;
        if ($userAgent !== null) {
            if (preg_match('/^[\x20-\x7e]+$/', $userAgent) !== 1 || trim($userAgent) === '') {
                throw new InvalidArgumentException('userAgent must be printable ASCII');
            }
            $agent .= ' ' . trim($userAgent);
        }

        $this->transport = new Transport(
            $httpClient,
            $apiKey,
            rtrim((string) $uri, '/'),
            $maxRetries,
            $connectTimeout,
            $timeout,
            $agent,
            $responseHook,
        );
        $this->proxies = new Feed($this->transport, '/feeds/proxies', ProxyEvent::class);
        $this->anonymizers = new Feed($this->transport, '/feeds/anonymizers', AnonymizerEvent::class);
        $this->torrents = new Feed($this->transport, '/feeds/torrents', TorrentEvent::class);
        $this->ja4t = new Feed($this->transport, '/feeds/ja4t', Ja4tEvent::class);
        $this->heliosHttp = new Feed($this->transport, '/feeds/helio/http', HeliosHttpEvent::class);
        $this->heliosHttps = new Feed($this->transport, '/feeds/helio/https', HeliosHttpsEvent::class);
        $this->heliosDns = new Feed($this->transport, '/feeds/helio/dns', HeliosDnsEvent::class);
        $this->heliosAdb = new Feed($this->transport, '/feeds/helio/adb', HeliosAdbEvent::class);
    }

    /**
     * Returns the account that owns the API key. It costs no credits.
     */
    public function account(): Account
    {
        return $this->transport->request(Account::class, 'GET', '/account/me', RetryPolicy::Transient);
    }

    /**
     * Checks that the API is up. It costs no credits, and an unhealthy API is reported at once.
     *
     * @throws ApiException with status 503 when the API is unhealthy
     */
    public function health(): void
    {
        $this->transport->request(null, 'GET', '/health', RetryPolicy::RateLimit);
    }

    /**
     * Looks up an IPv4 or IPv6 address. It costs one credit; failed lookups are not billed.
     * Private and reserved addresses are rejected with a 422 ApiException.
     */
    public function lookupIp(string $ip): IpInfo
    {
        $canonical = self::canonicalIp($ip) ?? throw new InvalidArgumentException('invalid IP address');

        return $this->transport->request(IpInfo::class, 'GET', "/lookup/ip/{$canonical}", RetryPolicy::Billed);
    }

    /**
     * Looks up as many as 1,000 addresses in one request.
     *
     * The API skips private and reserved addresses and collapses duplicates, so there can be
     * fewer results than addresses. Match results to inputs by their canonical ip, not by
     * position. A batch of n distinct addresses costs ceil(n * 0.9) credits, and nothing is
     * billed when the team cannot cover the whole batch.
     *
     * @param list<string> $ips
     *
     * @return list<IpInfo>
     */
    public function lookupIps(array $ips): array
    {
        if ($ips === []) {
            return [];
        }
        if (\count($ips) > self::MAX_BATCH_SIZE) {
            throw new InvalidArgumentException(\count($ips) . ' addresses exceed the batch limit of ' . self::MAX_BATCH_SIZE);
        }
        $canonical = [];
        foreach ($ips as $index => $ip) {
            $canonical[] = self::canonicalIp($ip)
                ?? throw new InvalidArgumentException("invalid IP address at index {$index}");
        }

        $batch = $this->transport->request(IpBatch::class, 'POST', '/lookup/ips', RetryPolicy::Billed, body: ['ips' => $canonical]);

        return $batch->results;
    }

    /**
     * Looks up the proxy traffic Synthient has observed for a domain over the last 30 days.
     * It costs one credit. Send internationalized names as Punycode.
     *
     * @param int|null $limit how many entries the top lists and recent events hold, from 1 to
     *                        1,000; the API defaults to 50
     */
    public function lookupDomain(string $domain, ?int $limit = null): DomainInfo
    {
        if ($domain === '' || $domain === '.' || $domain === '..') {
            throw new InvalidArgumentException('invalid domain');
        }
        if ($limit !== null && ($limit < 1 || $limit > 1000)) {
            throw new InvalidArgumentException('limit must be from 1 to 1,000');
        }

        $path = '/lookup/domain/' . rawurlencode($domain);

        return $this->transport->request(DomainInfo::class, 'GET', $path, RetryPolicy::Billed, $limit === null ? [] : ['limit' => $limit]);
    }

    private static function canonicalIp(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $packed = inet_pton($ip);

        return $packed === false ? null : (inet_ntop($packed) ?: null);
    }
}
