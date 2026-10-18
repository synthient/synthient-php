<p align="center">
  <a href="https://synthient.com">
    <img src="https://raw.githubusercontent.com/synthient/synthient-php/main/assets/logo.svg" height="96" alt="Synthient logo">
  </a>
</p>
<h3 align="center">Synthient</h3>
<p align="center">High-fidelity data on residential proxies, VPNs, and other anonymized IPs.</p>
<p align="center">
  <a href="https://docs.synthient.com">Documentation</a> ·
  <a href="https://packagist.org/packages/synthient/synthient-php">Packagist</a> ·
  <a href="https://docs.synthient.com/misc/support">Support</a>
</p>

# synthient-php

`synthient-php` is the official PHP SDK for the [Synthient API](https://docs.synthient.com): IP and domain intelligence, account details, and real-time and bulk data feeds.

[![Packagist](https://img.shields.io/packagist/v/synthient/synthient-php)](https://packagist.org/packages/synthient/synthient-php)
[![test](https://github.com/synthient/synthient-php/actions/workflows/test.yml/badge.svg)](https://github.com/synthient/synthient-php/actions/workflows/test.yml)
[![lint](https://github.com/synthient/synthient-php/actions/workflows/lint.yml/badge.svg)](https://github.com/synthient/synthient-php/actions/workflows/lint.yml)
[![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](https://github.com/synthient/synthient-php/blob/main/LICENSE)

## Table of Contents

- [synthient-php](#synthient-php)
  - [SDK Installation](#sdk-installation)
  - [Requirements](#requirements)
  - [API Keys](#api-keys)
  - [Authentication](#authentication)
  - [SDK Example Usage](#sdk-example-usage)
  - [Available Resources and Operations](#available-resources-and-operations)
  - [Streaming](#streaming)
  - [Pagination](#pagination)
  - [Downloads](#downloads)
  - [Retries](#retries)
  - [Timeouts](#timeouts)
  - [Error Handling](#error-handling)
  - [Server Selection](#server-selection)
  - [Custom HTTP Client](#custom-http-client)
  - [Debugging](#debugging)
  - [gRPC Schema](#grpc-schema)
- [Development](#development)
  - [Maturity](#maturity)
  - [Contributions](#contributions)
  - [License](#license)

## SDK Installation

```sh
composer require synthient/synthient-php
```

Composer also installs [Guzzle](https://docs.guzzlephp.org), which the SDK uses to send requests.

## Requirements

PHP 8.3 or later on a 64-bit build, and Guzzle 7.15.2 or later or 8.0.1 or later. Guzzle uses the cURL extension when it is available. With the default HTTP client, streams and downloads go through Guzzle's PHP stream handler, so they need the `allow_url_fopen` ini setting, which is on by default.

## API Keys

Every request to the HTTP API needs a Synthient API key. Keys are issued from the Synthient dashboard; if you don't have one yet, [contact us](mailto:contact@synthient.com). Each key carries scopes that decide what it can call. `BASIC` covers the lookups and `account()`, and each feed has one scope for its real-time stream and one for its exports, listed under [Feeds](#feeds). See [Authentication](https://docs.synthient.com/authentication) for how scopes work.

If a request fails even though you are sending a key, it is usually one of these:

- **401 Unauthorized**: the key is malformed, unknown, or revoked. Check that it was loaded from the environment and isn't truncated.
- **402 Payment Required**: the team is out of lookup credits. `account()` reports the remaining balance and when it resets.
- **403 Forbidden**: the key lacks the scope for that endpoint or feed. `account()` lists the key's scopes.

## Authentication

This SDK supports the following security scheme:

| Name     | Type    | Scheme             | Environment Variable |
| -------- | ------- | ------------------ | -------------------- |
| `apiKey` | API key | `x-api-key` header | `SYNTHIENT_API_KEY`  |

With no arguments, the client reads the key from the `SYNTHIENT_API_KEY` environment variable. To use a key from somewhere else, such as a secret manager, pass it as `apiKey` instead of writing it into your code:

```php
$client = new Synthient\Client(); // reads SYNTHIENT_API_KEY

$client = new Synthient\Client(apiKey: $apiKey);
```

The constructor trims surrounding whitespace from the key, then throws `InvalidArgumentException` when it is empty or holds anything other than visible ASCII characters. It makes no network calls, so an invalid key shows up as a 401 on the first request. The key is sent only to the Synthient API, never to the storage host that serves export downloads.

## SDK Example Usage

### Look up an IP address

Get the network, location, and risk intelligence for an IPv4 or IPv6 address. Each lookup costs one credit, and failed lookups are free.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$client = new Synthient\Client();

$info = $client->lookupIp('8.8.8.8');
if ($info->intelligence !== null) {
    echo $info->intelligence->riskScore, ' ', implode(', ', $info->intelligence->categories), "\n";
}
```

`network`, `location`, and `intelligence` are null when the API has no data for that section, so check them before reading their properties. The examples below reuse `$client`. Runnable programs are in [examples](https://github.com/synthient/synthient-php/tree/main/examples), for example `php examples/lookup_ip.php 8.8.8.8`.

### Look up many IP addresses

Look up as many as 1000 addresses in one request, billed at `ceil(n * 0.9)` credits for the `n` distinct addresses looked up. Private and reserved addresses are skipped and duplicates collapse into one result, so match results by their canonical `ip` rather than by position. The SDK sends each address in canonical form, so `2001:4860:4860:0:0:0:0:8888` comes back as `2001:4860:4860::8888`.

```php
foreach ($client->lookupIps(['8.8.8.8', '1.1.1.1']) as $result) {
    if ($result->intelligence !== null) {
        echo $result->ip, ' ', $result->intelligence->riskScore, "\n";
    }
}
```

### Look up a domain

Get the honeypot traffic observed for a domain, with its most recent events. `limit` caps the number of recent events, 50 by default. A domain that has never been observed is inactive with zero counts rather than an error. Each lookup costs one credit.

```php
$domain = $client->lookupDomain('example.com', limit: 10);
echo $domain->status, ' ', $domain->stats->events24h, "\n";
foreach ($domain->recentEvents as $event) {
    echo $event->timestamp->format(DATE_ATOM), ' ', $event->targetSubdomain, ' ', $event->port, "\n";
}
```

### Check your account

Get the account that owns the API key, with the key's scopes and the team's remaining lookup credits. It costs no credits.

```php
$account = $client->account();
echo $account->organization->name, ' ', implode(', ', $account->scopes), ' ', $account->lookupQuota->credits, "\n";
```

## Available Resources and Operations

<details open>
<summary>Available methods</summary>

### Client

- `lookupIp` - Look up an IPv4 or IPv6 address
- `lookupIps` - Look up as many as 1000 addresses in one request
- `lookupDomain` - Look up the honeypot traffic observed for a domain
- `account` - Get the account that owns the API key, the key's scopes, and the team's remaining lookup credits
- `health` - Check that the API and its dependencies are healthy

### Feeds

Each feed is a property of the client, such as `$client->proxies`, and has the same methods:

- `stream` - Connect to the feed's real-time events
- `listExports` - List one page of the feed's Parquet exports, newest first
- `allExports` - Iterate over every export, fetching pages as needed
- `exportMeta` - Get an export's checksum, size, row count, and Parquet schema
- `download` - Open an export's Parquet file for reading
- `downloadUrl` - Get a presigned URL for an export's Parquet file, valid for 24 hours

| Property      | Data                                          | Event class        | Stream scope            | Export scope          |
| ------------- | --------------------------------------------- | ------------------ | ----------------------- | --------------------- |
| `proxies`     | Residential, mobile, and datacenter proxies   | `ProxyEvent`       | `PROXY_FIREHOSE`        | `PROXY_FEEDS`         |
| `anonymizers` | VPN and anonymizer IP ranges                  | `AnonymizerEvent`  | `ANONYMIZERS_STREAM`    | `ANONYMIZERS_FEED`    |
| `torrents`    | BitTorrent DHT and tracker peers              | `TorrentEvent`     | `TORRENTS_STREAM`       | `TORRENTS_FEED`       |
| `ja4t`        | JA4T TCP fingerprints                         | `Ja4tEvent`        | `JA4T_STREAM`           | `JA4T_FEED`           |
| `heliosHttp`  | Helios honeypot HTTP requests                 | `HeliosHttpEvent`  | `HONEYPOT_HTTP_STREAM`  | `HONEYPOT_HTTP_FEED`  |
| `heliosHttps` | Helios honeypot TLS ClientHellos              | `HeliosHttpsEvent` | `HONEYPOT_HTTPS_STREAM` | `HONEYPOT_HTTPS_FEED` |
| `heliosDns`   | Helios honeypot tunnel destinations           | `HeliosDnsEvent`   | `HONEYPOT_DNS_STREAM`   | `HONEYPOT_DNS_FEED`   |
| `heliosAdb`   | Helios honeypot Android Debug Bridge commands | `HeliosAdbEvent`   | `HONEYPOT_ADB_STREAM`   | `HONEYPOT_ADB_FEED`   |

The event classes are in the `Synthient\Event` namespace, and the scope names are constants of `Synthient\Enum\Scope`.

</details>

## Streaming

`stream()` connects to a feed's real-time events and returns an `EventStream` to iterate once. Errors such as a missing scope (403) or too many open streams (429) are thrown when it connects, before iteration. The server keeps a stream open for up to 30 minutes and then closes it, which ends the loop without an error. The API has no resume cursor, so connect again to continue. Close the stream in a `finally` block so that leaving the loop early releases the connection:

```php
use Synthient\Exception\ApiException;
use Synthient\Exception\SynthientException;

while (true) {
    try {
        $events = $client->proxies->stream();
        try {
            foreach ($events as $event) {
                echo $event->ip, ' ', $event->provider, ' ', $event->type, "\n";
            }
        } finally {
            $events->close();
        }
    } catch (SynthientException $e) {
        fwrite(STDERR, "proxy stream: {$e->getMessage()}\n");
        if ($e instanceof ApiException && in_array($e->statusCode, [401, 403], true)) {
            break;
        }
        // Pause after a failure so a failing stream doesn't reconnect in a tight loop.
        sleep(2);
    }
}
```

The loop stops on a 401 or 403, because reconnecting can't fix a rejected key or a missing scope. During iteration, a read that fails or times out throws a `ConnectionException` or `TimeoutException`, and a malformed event throws a `SynthientException`; after any of these the loop waits 2 seconds before reconnecting. A connection that closes between events ends the loop without an error, as the server's routine close does, and the loop then reconnects at once. A 429 `ApiException` without `retryAfter` means the key already has too many open streams. Iterating the same stream twice throws a `LogicException`.

## Pagination

`listExports()` returns one page of a feed's exports, newest first:

```php
$page = $client->proxies->listExports(limit: 50);
foreach ($page->exports as $export) {
    echo $export->id, ' ', $export->kind, ' ', $export->rowCount, "\n";
}
```

To get the next page, pass `$page->nextCursor` as `cursor`. It is null or empty on the last page. `allExports()` does the paging for you. It returns a generator that fetches pages as the loop needs them and stops fetching when the loop breaks:

```php
foreach ($client->proxies->allExports() as $export) {
    echo $export->id, ' ', $export->kind, ' ', $export->rowCount, "\n";
}
```

On the first page, the newest hourly export has the ID `'latest'`, a moving alias for the freshest export. To keep a stable ID, build it from the export's `date` and `hour`, such as `'2026-10-14/21'`.

## Downloads

Exports are Parquet files. Identify one by the `id` of an `Export` from a listing, by `'latest'` for the freshest one, by a UTC day as `'YYYY-MM-DD'` or a UTC hour as `'YYYY-MM-DD/H'`, or by a `DateTimeInterface`, which names the daily export of its UTC date. To build an hourly ID, format a UTC time with `'Y-m-d/G'`. `download()` returns a PSR-7 stream, which you must close:

```php
$id = (new DateTimeImmutable('yesterday', new DateTimeZone('UTC')))->format('Y-m-d');
$body = $client->proxies->download($id);
$file = fopen('proxies.parquet', 'wb') ?: throw new RuntimeException('cannot open proxies.parquet');
$hash = hash_init('sha256');
try {
    while (!$body->eof()) {
        $chunk = $body->read(1 << 20);
        if (fwrite($file, $chunk) !== strlen($chunk)) {
            throw new RuntimeException('cannot write proxies.parquet');
        }
        hash_update($hash, $chunk);
    }
} finally {
    $body->close();
    fclose($file);
}
if (!hash_equals($client->proxies->exportMeta($id)->checksum, hash_final($hash))) {
    throw new RuntimeException('proxies.parquet does not match the export checksum');
}
```

A connection that closes early can end the download without an error, so compare the file's SHA-256 with the export's `checksum`, as above. Use a stable ID for that, because `'latest'` can move to a newer export between the two calls. The file comes from a presigned storage URL that is requested without your API key, and that request is never retried. `downloadUrl()` returns the presigned URL itself for tools that read Parquet over HTTPS. The URL grants access without a key for 24 hours, so treat it as a secret.

An hourly export is available until its day's daily export is rolled up, shortly after 00:30 UTC the next day. After that, requests for the hour return a 400. While a daily export is being rolled up, requests for it return a 404 with `retryAfter` set.

## Retries

The client retries a failed request up to twice by default, backing off exponentially with jitter and honoring `Retry-After`:

- Account and feed requests are retried on 500, 502, 503, and 504 responses, on transport failures other than TLS errors, and on 429 responses that carry `Retry-After`. A failure while reading a stream or download is not retried, and neither is a download's request to the storage host.
- Billed lookups (`lookupIp`, `lookupIps`, and `lookupDomain`) are retried only on 429 responses that carry `Retry-After`, which the rate limiter returns before doing any work, so a lookup is never charged twice. When Guzzle uses cURL, each lookup also opens a fresh connection, because libcurl can silently resend a request whose reused connection drops. `health()` is retried the same way, so an unhealthy API is reported at once.

A 429 without `Retry-After` means the key's monthly request budget is spent or it has too many open streams, so it is thrown without a retry. A `Retry-After` longer than a minute is thrown to you instead of waited out.

To change the number of retries, pass `maxRetries` when creating the client. Zero disables retries:

```php
$client = new Synthient\Client(maxRetries: 4);
```

## Timeouts

The client waits up to 10 seconds (`connectTimeout`) for a connection and up to 60 seconds (`timeout`) for each attempt's response. Streams and downloads, and every request when the cURL extension is missing, go through Guzzle's PHP stream handler, which ignores `connectTimeout` and waits up to `timeout` for the connection as well. On a stream or download, `timeout` then bounds each wait for the next data rather than the whole response, and the API sends a stream keepalive every 15 seconds. Each retry gets a fresh timeout, so the timeout does not bound a whole call. Both are in seconds:

```php
$client = new Synthient\Client(connectTimeout: 5.0, timeout: 30.0);
```

A connection, a response, or a read that takes too long throws a `TimeoutException`.

## Error Handling

A request that fails with an error status throws a `Synthient\Exception\ApiException`. It has the following properties:

| Property     | Type                          | Description                                                                                      |
| ------------ | ----------------------------- | ------------------------------------------------------------------------------------------------ |
| `statusCode` | `int`                         | HTTP response status code, such as `404`                                                         |
| `type`       | `?string`                     | URI identifying the problem type, such as `https://docs.synthient.com/errors/validation-failed` |
| `title`      | `?string`                     | Short summary of the problem type                                                                |
| `detail`     | `?string`                     | Explanation of this occurrence. For a plain-text error, the response body                        |
| `errors`     | `array<string, list<string>>` | Validation messages by request field                                                             |
| `requestId`  | `?string`                     | Request ID to give Synthient support                                                             |
| `retryAfter` | `?int`                        | Seconds the server asked you to wait before retrying, or null                                    |
| `headers`    | `string[][]`                  | The response headers                                                                             |

`getMessage()` returns only the HTTP status, so it is safe to log. The other properties hold untrusted server data that can be sensitive, so log them deliberately.

### Example

```php
use Synthient\Exception\ApiException;

try {
    $info = $client->lookupIp('8.8.8.8');
    echo $info->ip, "\n";
} catch (ApiException $e) {
    if ($e->statusCode === 402) {
        echo "out of lookup credits\n";
    } elseif ($e->statusCode === 429 && $e->retryAfter !== null) {
        echo "rate limited, retry after {$e->retryAfter} seconds\n";
    } elseif ($e->statusCode === 429) {
        echo "monthly request budget spent\n";
    } else {
        echo "request {$e->requestId} failed: ", $e->detail ?? $e->title, "\n";
    }
}
```

### Error Types

All of these are in the `Synthient\Exception` namespace and extend `SynthientException`:

- `ApiException`: the API, or the storage behind an export download, answered with an error status.
- `ConnectionException`: a network failure stopped the request. A billed lookup that fails this way may or may not have been charged, so it is never retried.
- `TimeoutException`: connecting, a response, or a read from a stream or download took longer than the client's timeout. It extends `ConnectionException`.
- `SynthientException`: the base class, also thrown for a malformed response, and for a stream or download through the default client when `allow_url_fopen` is off.

An invalid argument, such as a malformed IP address or export ID, throws `InvalidArgumentException` before any request is made.

<details><summary>Status codes</summary>

<br />

| Status | Meaning                                                                                                            |
| ------ | ------------------------------------------------------------------------------------------------------------------ |
| 400    | The request is invalid, such as asking for an hourly export that has been rolled up                                |
| 401    | The API key is malformed, unknown, or revoked                                                                      |
| 402    | The team is out of lookup credits                                                                                  |
| 403    | The key lacks the scope for the endpoint or feed                                                                   |
| 404    | The export does not exist, or a daily export is still being rolled up and `retryAfter` is set                      |
| 422    | The input is invalid, such as a private or reserved address, a malformed domain, or a batch with no usable address |
| 429    | With `retryAfter`, the rate limit was hit. Without it, the request budget is spent or too many streams are open    |
| 5xx    | The server failed. Account and feed requests are retried on 500, 502, 503, and 504                                 |

</details>

## Server Selection

### Override Server URL Per-Client

The default server is `https://api.synthient.com/api/v4`. To send requests elsewhere, such as through a gateway or to a mock server in tests, pass `baseUrl` when creating the client. The URL includes the version path and must use http or https, with no query, fragment, or credentials:

```php
$client = new Synthient\Client(baseUrl: 'http://localhost:8080/api/v4');
```

## Custom HTTP Client

The client sends requests with Guzzle. Pass your own `GuzzleHttp\ClientInterface` as `httpClient`, for example to route requests through a proxy:

```php
$client = new Synthient\Client(
    httpClient: new GuzzleHttp\Client(['proxy' => 'http://proxy.example.com:8080']),
);
```

Your client keeps its defaults, such as `proxy`, `verify`, and `cert`, but the SDK sets `allow_redirects`, `http_errors`, `stream`, and the timeouts on every request, and export downloads drop its default headers, cookies, and auth. It must not retry requests or set a default query, and streams and downloads need a handler that streams response bodies. Guzzle's default handler does only when `allow_url_fopen` is on, and the SDK checks that setting only for its own default client, so through your client a stream without it buffers until `timeout` runs out. Lookups and `health()` add `CURLOPT_FRESH_CONNECT` to its `curl` option, so a client that requires persistent cURL connection sharing can't send them.

To identify your application, `userAgent: 'my-app/1.2'` appends a product token to the `User-Agent` header.

## Debugging

`responseHook` is called after every API response arrives, including each retry, page, stream handshake, and export redirect. It receives a `Synthient\ResponseMetadata` with the status code, the request ID to give Synthient support, the operation's rate limit, and the response headers:

```php
$client = new Synthient\Client(responseHook: function (Synthient\ResponseMetadata $meta): void {
    error_log("status={$meta->statusCode} request_id={$meta->requestId}");
    if ($meta->rateLimit !== null) {
        error_log("rate limit: {$meta->rateLimit->remaining} of {$meta->rateLimit->limit} left");
    }
});
```

The hook runs synchronously, and an exception it throws reaches your code. Storage download responses are not passed to it.

> [!WARNING]
> Response headers can contain secrets: an export redirect's `Location` header is a presigned download URL. Avoid logging `$meta->headers` in production.

## gRPC Schema

Unlike the other Synthient SDKs, this package has no gRPC schema fetcher, because PHP's gRPC support needs a compiled extension. To get the protobuf schema of the [Synthient gRPC API](https://docs.synthient.com/grpc), use another SDK's fetcher or any gRPC reflection client, such as [grpcurl](https://github.com/fullstorydev/grpcurl):

```sh
grpcurl -H "x-api-key: $SYNTHIENT_API_KEY" -protoset-out synthient.protoset grpc.synthient.com:443 describe synthient.v1.SynthientService
```

`describe` prints the service's methods, and `-protoset-out` writes the full schema, the service and every message it uses, to `synthient.protoset` as a `FileDescriptorSet`.

# Development

## Maturity

This SDK follows [Semantic Versioning](https://semver.org). Breaking changes to its public API ship only in a new major version, so the `^1.0` constraint that `composer require` adds accepts every compatible release.

## Contributions

Bug reports and feature requests are welcome as [GitHub issues](https://github.com/synthient/synthient-php/issues). For questions about the API or your account, contact [Synthient support](https://docs.synthient.com/misc/support).

## License

[Apache 2.0](https://github.com/synthient/synthient-php/blob/main/LICENSE)
