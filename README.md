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
  - [Installation](#installation)
  - [Quick Start](#quick-start)
  - [Authentication](#authentication)
  - [Available Resources and Operations](#available-resources-and-operations)
  - [IP and Domain Lookups](#ip-and-domain-lookups)
  - [Account](#account)
  - [Feeds](#feeds)
  - [Errors](#errors)
  - [Retries and Timeouts](#retries-and-timeouts)
  - [Configuration](#configuration)
  - [Response Metadata](#response-metadata)
  - [gRPC Schema](#grpc-schema)
  - [Requirements](#requirements)
  - [Versioning](#versioning)
  - [Documentation](#documentation)
  - [Contributions](#contributions)
  - [License](#license)

## Installation

```sh
composer require synthient/synthient-php
```

Composer also installs [Guzzle](https://docs.guzzlephp.org), which the SDK uses to send requests.

## Quick Start

Look up an IP address and print its risk score and categories. The lookup costs one credit.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$client = new Synthient\Client();

$info = $client->lookupIp('8.8.8.8');
if ($info->intelligence !== null) {
    echo $info->intelligence->riskScore, ' ', implode(', ', $info->intelligence->categories), "\n";
}
```

With no arguments, the client reads the API key from the `SYNTHIENT_API_KEY` environment variable. The examples below reuse `$client`, and runnable programs are in [examples](https://github.com/synthient/synthient-php/tree/main/examples).

## Authentication

This SDK supports the following security scheme:

| Name     | Type    | Scheme             | Environment Variable |
| -------- | ------- | ------------------ | -------------------- |
| `apiKey` | API key | `x-api-key` header | `SYNTHIENT_API_KEY`  |

With no arguments, the client reads `SYNTHIENT_API_KEY`. To use a key from a secret manager, pass it as `apiKey`:

```php
$client = new Synthient\Client(); // reads SYNTHIENT_API_KEY

$client = new Synthient\Client(apiKey: $apiKey);
```

The constructor makes no network calls. To check a key, call `account()`, which costs no credits.

See [Authentication](https://docs.synthient.com/authentication) in the API documentation.

### API Keys

Every request needs a Synthient API key. Keys are issued from the Synthient dashboard; if you don't have one, [contact us](mailto:contact@synthient.com). A key's scopes decide what it can call: `BASIC` covers the lookups and `account()`, and each feed has its own scopes, listed under [Feeds](#feeds).

If a request fails even though you are sending a key:

- **401 Unauthorized**: the key is malformed, unknown, or revoked.
- **402 Payment Required**: the team is out of lookup credits.
- **403 Forbidden**: the key lacks the scope for that endpoint or feed.

`account()` shows the key's scopes and the team's remaining credits.

## Available Resources and Operations

<details open>
<summary>Available methods</summary>

### Client

- [`lookupIp`](#look-up-an-ip-address) - Look up an IPv4 or IPv6 address
- [`lookupIps`](#look-up-many-ip-addresses) - Look up as many as 1000 addresses in one request
- [`lookupDomain`](#look-up-a-domain) - Look up the honeypot traffic observed for a domain
- [`account`](#account) - Get the key's account, scopes, and remaining lookup credits
- [`health`](#check-the-apis-health) - Check that the API and its dependencies are healthy

### Feed

Each [feed](#feeds) is a property of the client, such as `$client->proxies`, and has the same methods:

- [`stream`](#streaming) - Connect to the feed's real-time events
- [`listExports`](#pagination) - List one page of the feed's Parquet exports, newest first
- [`allExports`](#pagination) - Iterate over every export, fetching pages as needed
- [`exportMeta`](#downloads) - Get an export's checksum, size, row count, and Parquet schema
- [`download`](#downloads) - Open an export's Parquet file for reading
- [`downloadUrl`](#downloads) - Get a presigned URL for an export's Parquet file, valid for 24 hours

</details>

## IP and Domain Lookups

The lookups need the `BASIC` scope. See [Lookups](https://docs.synthient.com/sdks/php/lookups) in the SDK documentation and the [IP API](https://docs.synthient.com/ipapi) reference.

### Look up an IP address

Get the network, location, and risk intelligence for an IPv4 or IPv6 address. Each lookup costs one credit; failed lookups are free.

```php
$info = $client->lookupIp('8.8.8.8');
if ($info->network !== null) {
    echo $info->network->isp, ' ', $info->network->type, "\n";
}
if ($info->location !== null) {
    echo $info->location->city, ', ', $info->location->country, "\n";
}
```

`network`, `location`, and `intelligence` are null when the API has no data for them. A private or reserved address is rejected with a 422 `ApiException`.

### Look up many IP addresses

Look up as many as 1000 addresses in one request for `ceil(n * 0.9)` credits. Private and reserved addresses are skipped and duplicates are merged, so match results by their canonical `ip`, such as `2001:4860:4860::8888`.

```php
foreach ($client->lookupIps(['8.8.8.8', '1.1.1.1']) as $result) {
    if ($result->intelligence !== null) {
        echo $result->ip, ' ', $result->intelligence->riskScore, "\n";
    }
}
```

An empty list returns `[]` without a request, and more than `Synthient\Client::MAX_BATCH_SIZE` addresses throw `InvalidArgumentException`. Nothing is billed when the team cannot cover the whole batch.

### Look up a domain

Get the honeypot traffic observed for a domain over the last 30 days and its most recent events. Each lookup costs one credit.

```php
$domain = $client->lookupDomain('example.com', limit: 10);
echo $domain->status, ' ', $domain->stats->events24h, "\n";
foreach ($domain->recentEvents as $event) {
    echo $event->timestamp->format(DATE_ATOM), ' ', $event->targetSubdomain, ' ', $event->port, "\n";
}
```

- `limit` is how many recent events to return. The API defaults to 50 and clamps values above 1000.
- Send internationalized names as Punycode.
- A domain that was never observed is `inactive` with zeroed stats, not an error.

## Account

Get the key's account, scopes, and remaining lookup credits, at no cost.

```php
$account = $client->account();
echo $account->organization->name, ' ', implode(', ', $account->scopes), ' ', $account->lookupQuota->credits, "\n";
```

`$account->lookupQuota->resetsIn` is the number of seconds until the credits reset.

### Check the API's health

`health()` returns nothing when the API is healthy and throws an `ApiException` with status 503 when it is not. It costs no credits.

```php
use Synthient\Exception\ApiException;

try {
    $client->health();
} catch (ApiException $e) {
    echo 'unhealthy: ', $e->getMessage(), "\n";
}
```

## Feeds

Each feed is a property of the client, such as `$client->proxies`, with a real-time stream and hourly and daily Parquet exports. Streaming and exports need separate scopes:

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

See [Feeds](https://docs.synthient.com/sdks/php/feeds) in the SDK documentation, and [Firehose](https://docs.synthient.com/enterprise/firehose) and [Feeds](https://docs.synthient.com/enterprise/feeds) for the data behind each feed.

### Streaming

`stream()` connects to a feed's real-time events and returns an `EventStream` to iterate once. Close it in a `finally` block to release the connection:

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
        // Avoid reconnecting in a tight loop.
        sleep(2);
    }
}
```

- The server closes each stream after up to 30 minutes. That ends the inner loop without an error, and the outer loop reconnects at once.
- A 401 or 403 stops the loop, because reconnecting can't fix a rejected key or a missing scope.
- Any other failure throws a `SynthientException`, and the loop waits 2 seconds before reconnecting.
- Events sent while disconnected are missed. The API has no resume cursor.

### Pagination

`listExports()` returns one page of a feed's exports, newest first. Pass `$page->nextCursor` as `cursor` to get the next page; it is null or empty on the last one.

```php
$page = $client->proxies->listExports(limit: 50);
foreach ($page->exports as $export) {
    echo $export->id, ' ', $export->kind, ' ', $export->rowCount, "\n";
}
```

`allExports()` fetches the pages for you as the loop needs them:

```php
foreach ($client->proxies->allExports() as $export) {
    echo $export->id, ' ', $export->kind, ' ', $export->rowCount, "\n";
}
```

On the first page, the newest hourly export has the ID `'latest'`, a moving alias for the freshest export. Its `date` and `hour` give its stable ID, such as `'2026-10-14/21'`.

`limit` sets the page size for both methods. The API defaults to 100 and clamps values above 500.

### Downloads

Exports are Parquet files. `download()` returns a file's contents as a PSR-7 stream, which you must close:

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

- Name an export by its `id` from a listing, by `'latest'`, by a UTC day or hour as `'YYYY-MM-DD'` or `'YYYY-MM-DD/H'`, or by a `DateTimeInterface` for its UTC day. Build an hourly ID with `format('Y-m-d/G')` on a time in UTC.
- A connection that closes early can end a download without an error, so compare the file's SHA-256 with `exportMeta()`'s `checksum`, as above. Use a fixed ID for both calls, because `'latest'` can move between them.
- `downloadUrl()` returns a presigned URL that works without a key for 24 hours, so treat it as a secret. Your API key is never sent to the storage host.
- Hourly exports roll into the daily export shortly after 00:30 UTC the next day. After that, the hour returns a 400.

## Errors

A request that fails with a non-success status throws a `Synthient\Exception\ApiException`:

| Property     | Type                          | Description                                   |
| ------------ | ----------------------------- | --------------------------------------------- |
| `statusCode` | `int`                         | HTTP status code, such as `404`               |
| `type`       | `?string`                     | URI identifying the problem type              |
| `title`      | `?string`                     | Short summary of the problem type             |
| `detail`     | `?string`                     | Explanation of this occurrence                |
| `errors`     | `array<string, list<string>>` | Validation messages by request field          |
| `requestId`  | `?string`                     | Request ID to give Synthient support          |
| `retryAfter` | `?int`                        | Seconds the server asked you to wait, or null |
| `headers`    | `string[][]`                  | The response headers                          |

`getMessage()` returns only the HTTP status, so it is safe to log. The other properties hold untrusted server data.

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

- `ApiException`: the API, or the storage host during `download()`, returned a non-success status.
- `ConnectionException`: a network failure stopped the request.
- `TimeoutException`: a `ConnectionException` for a connection, response, or read that took longer than the timeout.
- `SynthientException`: the base class, also thrown for a response that can't be decoded.

Invalid arguments, such as a missing API key or a malformed IP address, throw `InvalidArgumentException` before any request is sent.

<details><summary>Status codes</summary>

<br />

| Status | Meaning                                                                                         |
| ------ | ----------------------------------------------------------------------------------------------- |
| 400    | The request is invalid, such as an hourly export that was rolled up                             |
| 401    | The API key is malformed, unknown, or revoked                                                   |
| 402    | The team is out of lookup credits                                                               |
| 403    | The key lacks the scope for the endpoint or feed                                                |
| 404    | The export doesn't exist, or a daily export is still rolling up (`retryAfter` is set)           |
| 422    | The input is invalid, such as a private address or a malformed domain                           |
| 429    | Rate limited if `retryAfter` is set; otherwise the budget is spent or too many streams are open |
| 5xx    | The server failed                                                                               |

</details>

See [Errors and retries](https://docs.synthient.com/sdks/php/errors) in the SDK documentation and [Errors](https://docs.synthient.com/errors) for what each status means.

## Retries and Timeouts

### Retries

The client retries a failed request up to twice, backing off exponentially with jitter and honoring `Retry-After`:

| Requests                      | Retried on                                                                                   |
| ----------------------------- | -------------------------------------------------------------------------------------------- |
| Account and feed requests     | 500, 502, 503, and 504, most transport errors, and 429 with `Retry-After`                    |
| Billed lookups and `health()` | Only 429 with `Retry-After`, which comes before any work, so a lookup is never charged twice |

The first retry waits about one second, and the wait doubles up to 30 seconds. A `Retry-After` longer than a minute is not waited for: the `ApiException` is thrown with its `retryAfter` instead. TLS failures are not retried.

A 429 without `Retry-After` means the monthly request budget is spent or too many streams are open, so it is thrown at once. Downloads from the storage host are never retried.

Set the number of retries with `maxRetries`. Zero disables them:

```php
$client = new Synthient\Client(maxRetries: 4);
```

### Timeouts

The client waits up to 10 seconds (`connectTimeout`) to connect and up to 60 seconds (`timeout`) for each attempt's response. Both are in seconds:

```php
$client = new Synthient\Client(connectTimeout: 5.0, timeout: 30.0);
```

- On a stream or download, `timeout` bounds each wait for the next data, not the whole transfer. The API sends a stream keepalive about every 15 seconds.
- Guzzle's PHP stream handler, which serves streams, downloads, and every request without the cURL extension, ignores `connectTimeout` and waits up to `timeout` to connect.
- Retries get a fresh timeout, so a call with retries can take longer than `timeout`.

See [Rate Limits](https://docs.synthient.com/rate-limits) for the limits behind a 429.

## Configuration

Every constructor argument is optional, so pass the ones you need by name:

| Option           | Type                               | Default                            | Description                                                 |
| ---------------- | ---------------------------------- | ---------------------------------- | ----------------------------------------------------------- |
| `apiKey`         | `?string`                          | `SYNTHIENT_API_KEY`                | The API key                                                 |
| `baseUrl`        | `string`                           | `https://api.synthient.com/api/v4` | The API base URL, including the version path                |
| `maxRetries`     | `int`                              | `2`                                | How many times a failed request is retried                  |
| `connectTimeout` | `float`                            | `10.0`                             | Seconds to wait for a connection                            |
| `timeout`        | `float`                            | `60.0`                             | Seconds each attempt waits for a response, or the next data |
| `httpClient`     | `?GuzzleHttp\ClientInterface`      | Guzzle's default client            | A Guzzle client, for proxies, TLS settings, or a handler    |
| `userAgent`      | `?string`                          | none                               | Appended to the `User-Agent` header                         |
| `responseHook`   | `?Closure(ResponseMetadata): void` | none                               | Called with every API response                              |

An invalid option throws `InvalidArgumentException`. See [Client](https://docs.synthient.com/sdks/php/client) in the SDK documentation.

### Server URL

The default server is `https://api.synthient.com/api/v4`. Use `baseUrl` to send requests elsewhere, such as a mock server in tests:

```php
$client = new Synthient\Client(baseUrl: 'http://localhost:8080/api/v4');
```

### Custom HTTP Client

Pass your own `GuzzleHttp\ClientInterface` as `httpClient`, for example to use a proxy:

```php
$client = new Synthient\Client(
    httpClient: new GuzzleHttp\Client(['proxy' => 'http://proxy.example.com:8080']),
);
```

- It keeps its defaults, such as `proxy`, `verify`, and `cert`, but the SDK sets `allow_redirects`, `http_errors`, `stream`, and the timeouts on every request. Export downloads drop its default headers, cookies, and auth.
- It must not retry requests, so a billed lookup is never sent twice. It must not set a default query either.
- Streams and downloads need a handler that streams response bodies. Guzzle's default does only when `allow_url_fopen` is on.
- Lookups and `health()` add `CURLOPT_FRESH_CONNECT` to its `curl` option, so a client that requires persistent cURL sharing can't send them.

`userAgent: 'my-app/1.2'` adds your product token to the `User-Agent` header.

## Response Metadata

`responseHook` is called after every API response, including retries, pages, stream handshakes, and export redirects, with a `Synthient\ResponseMetadata` holding the status, request ID, rate limit, and headers:

```php
$client = new Synthient\Client(responseHook: function (Synthient\ResponseMetadata $meta): void {
    error_log("status={$meta->statusCode} request_id={$meta->requestId}");
    if ($meta->rateLimit !== null) {
        error_log("rate limit: {$meta->rateLimit->remaining} of {$meta->rateLimit->limit} left");
    }
});
```

| Property     | Type                   | Description                                                               |
| ------------ | ---------------------- | ------------------------------------------------------------------------- |
| `statusCode` | `int`                  | HTTP status code of the response                                          |
| `requestId`  | `?string`              | The `X-Request-Id` header, which support can trace                        |
| `rateLimit`  | `?Synthient\RateLimit` | `limit`, `remaining`, and `reset` from the `RateLimit-*` headers, or null |
| `headers`    | `string[][]`           | The response headers                                                      |

`reset` is the number of seconds until the rate limit bucket is full again. Limits apply per team and are shared by all of its keys; see [Rate Limits](https://docs.synthient.com/rate-limits). The storage host's responses to export downloads don't reach the hook.

An exception the hook throws reaches your code.

> [!WARNING]
> Response headers can contain secrets, such as presigned download URLs. Avoid logging `$meta->headers` in production.

## gRPC Schema

Unlike the other Synthient SDKs, this package has no gRPC schema fetcher, because PHP's gRPC support needs a compiled extension. To get the protobuf schema of the [Synthient gRPC API](https://docs.synthient.com/grpc), use another SDK's fetcher or a reflection client such as [grpcurl](https://github.com/fullstorydev/grpcurl):

```sh
grpcurl -H "x-api-key: $SYNTHIENT_API_KEY" -protoset-out synthient.protoset grpc.synthient.com:443 describe synthient.v1.SynthientService
```

`-protoset-out` writes the service and every message it uses to `synthient.protoset` as a `FileDescriptorSet`.

## Requirements

PHP 8.3 or later on a 64-bit build, with Guzzle 7.15.2 or later or 8.0.1 or later. With the default HTTP client, streams and downloads go through Guzzle's PHP stream handler, which needs the `allow_url_fopen` ini setting. It is on by default.

## Versioning

This SDK follows [Semantic Versioning](https://semver.org). Breaking changes ship only in a new major version, so the `^1.0` constraint that `composer require` adds accepts every compatible release.

## Documentation

- [PHP SDK](https://docs.synthient.com/sdks/php): getting started
- [Client](https://docs.synthient.com/sdks/php/client): the constructor, its options, and response metadata
- [Lookups](https://docs.synthient.com/sdks/php/lookups): IP and domain lookups, the account, and the API's health
- [Feeds](https://docs.synthient.com/sdks/php/feeds): streams and Parquet exports
- [Errors and retries](https://docs.synthient.com/sdks/php/errors): exceptions, retries, and timeouts
- [Types](https://docs.synthient.com/sdks/php/types): the fields of every response and event class

## Contributions

Bug reports and feature requests are welcome as [GitHub issues](https://github.com/synthient/synthient-php/issues). For questions about the API or your account, contact [Synthient support](https://docs.synthient.com/misc/support).

## License

[Apache 2.0](https://github.com/synthient/synthient-php/blob/main/LICENSE)
