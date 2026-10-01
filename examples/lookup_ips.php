<?php

/*
 * Looks up several IP addresses in one request.
 *
 *     SYNTHIENT_API_KEY=... php examples/lookup_ips.php 8.8.8.8 1.1.1.1
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$ips = array_slice($argv ?? [], 1) ?: exit("usage: php examples/lookup_ips.php <ip>...\n");
$client = new Synthient\Client();

foreach ($client->lookupIps($ips) as $result) {
    if ($result->intelligence === null) {
        echo "{$result->ip}: no intelligence\n";

        continue;
    }
    echo "{$result->ip}: risk {$result->intelligence->riskScore}, ", implode(', ', $result->intelligence->categories) ?: 'no categories', "\n";
}
