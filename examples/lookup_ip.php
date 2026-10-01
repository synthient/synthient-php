<?php

/*
 * Looks up one IP address.
 *
 *     SYNTHIENT_API_KEY=... php examples/lookup_ip.php 8.8.8.8
 */

declare(strict_types=1);

use Synthient\Exception\ApiException;

require __DIR__ . '/../vendor/autoload.php';

$ip = $argv[1] ?? exit("usage: php examples/lookup_ip.php <ip>\n");
$client = new Synthient\Client();

try {
    $info = $client->lookupIp($ip);
} catch (ApiException $e) {
    if ($e->statusCode === 402) {
        exit("out of lookup credits\n");
    }

    throw $e;
}

echo "ip: {$info->ip}\n";
if ($info->network !== null) {
    echo "network: AS{$info->network->asn} {$info->network->isp} ({$info->network->type})\n";
}
if ($info->location !== null) {
    echo "location: {$info->location->city}, {$info->location->state}, {$info->location->country}\n";
}
if ($info->intelligence !== null) {
    echo "risk score: {$info->intelligence->riskScore}\n";
    echo 'categories: ', implode(', ', $info->intelligence->categories), "\n";
    foreach ($info->intelligence->providers as $provider) {
        echo "provider: {$provider->name} ({$provider->type}), last seen {$provider->lastSeen->format(DATE_ATOM)}\n";
    }
}
