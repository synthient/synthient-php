# synthient-php

![Synthient](https://raw.githubusercontent.com/synthient/synthient-php/main/assets/readme-header.svg)

[![Packagist](https://img.shields.io/packagist/v/synthient/synthient-php)](https://packagist.org/packages/synthient/synthient-php)
[![test](https://github.com/synthient/synthient-php/actions/workflows/test.yml/badge.svg)](https://github.com/synthient/synthient-php/actions/workflows/test.yml)
[![lint](https://github.com/synthient/synthient-php/actions/workflows/lint.yml/badge.svg)](https://github.com/synthient/synthient-php/actions/workflows/lint.yml)

The official PHP client for the [Synthient API](https://docs.synthient.com): IP and domain intelligence, account details, and real-time and bulk data feeds.

## Install

```sh
composer require synthient/synthient-php
```

Requires PHP 8.3 or later and Guzzle 7 or 8.

## Usage

```php
$client = new Synthient\Client(); // reads SYNTHIENT_API_KEY

$info = $client->lookupIp('8.8.8.8');
if ($info->intelligence !== null) {
    echo $info->intelligence->riskScore, ' ', implode(', ', $info->intelligence->categories), "\n";
}
```

Look up many addresses in one request:

```php
foreach ($client->lookupIps(['8.8.8.8', '1.1.1.1']) as $result) {
    echo $result->ip, ' ', $result->intelligence?->riskScore, "\n";
}
```

Consume a real-time feed:

```php
$events = $client->proxies->stream();
try {
    foreach ($events as $event) {
        echo $event->ip, ' ', $event->provider, "\n";
    }
} finally {
    $events->close();
}
```

The server ends a stream after about 30 minutes, so reconnect in a loop.

## Documentation

Every class and method is documented in its PHPDoc, including errors, retries, exports, and downloads. Runnable programs are in [examples](https://github.com/synthient/synthient-php/tree/main/examples), for example `php examples/lookup_ip.php 8.8.8.8`. Guides and field references are at [docs.synthient.com](https://docs.synthient.com).

Unlike the Go, Python, Java, and Node SDKs, this package has no gRPC schema fetcher, because PHP's gRPC support needs a compiled extension. To get the protobuf schema of the Synthient gRPC API, use one of those SDKs' `grpcschema` packages or any gRPC reflection client such as [grpcurl](https://github.com/fullstorydev/grpcurl).

## License

[Apache 2.0](https://github.com/synthient/synthient-php/blob/main/LICENSE)
