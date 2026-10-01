<?php

/*
 * Prints the proxy traffic observed for a domain over the last 30 days.
 *
 *     SYNTHIENT_API_KEY=... php examples/lookup_domain.php example.com
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$domain = $argv[1] ?? exit("usage: php examples/lookup_domain.php <domain>\n");
$client = new Synthient\Client();
$info = $client->lookupDomain($domain, limit: 5);

echo "{$info->domain}: {$info->status}\n";
echo "events: {$info->stats->events24h} in 24 hours, {$info->stats->totalEvents30d} in 30 days\n";
echo "unique IPs: {$info->uniqueIps->value24h} in 24 hours, {$info->uniqueIps->value30d} in 30 days\n";
echo "cadence: {$info->activityStats->cadence}\n";
foreach ($info->topSubdomains as $subdomain) {
    echo "subdomain: {$subdomain->subdomain} ({$subdomain->count})\n";
}
foreach ($info->recentEvents as $event) {
    echo "event: {$event->timestamp->format(DATE_ATOM)} {$event->sourceIp} -> {$event->targetSubdomain}:{$event->port}\n";
}
