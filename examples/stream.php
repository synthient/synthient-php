<?php

/*
 * Prints proxies from the real-time feed until interrupted, reconnecting when the server
 * ends the stream. Needs the PROXY_FIREHOSE scope.
 *
 *     SYNTHIENT_API_KEY=... php examples/stream.php
 */

declare(strict_types=1);

use Synthient\Exception\ApiException;
use Synthient\Exception\SynthientException;

require __DIR__ . '/../vendor/autoload.php';

$client = new Synthient\Client();

while (true) {
    try {
        $events = $client->proxies->stream();
        try {
            foreach ($events as $event) {
                echo "{$event->ip} {$event->provider} {$event->type}\n";
            }
        } finally {
            $events->close();
        }
    } catch (SynthientException $e) {
        if ($e instanceof ApiException && in_array($e->statusCode, [401, 403], true)) {
            exit("{$e->getMessage()}: check the key and its PROXY_FIREHOSE scope\n");
        }
        fwrite(STDERR, "{$e->getMessage()}, reconnecting\n");
    }
    // Stay under the limit of 30 stream connections a minute.
    sleep(2);
}
