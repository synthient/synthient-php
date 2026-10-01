<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Synthient\Client;
use Synthient\Exception\ApiException;

require __DIR__ . '/vendor/autoload.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "consumer check failed: {$message}\n");
        exit(1);
    }
}

$installed = __DIR__ . '/vendor/synthient/synthient-php';
check(str_starts_with((string) (new ReflectionClass(Client::class))->getFileName(), $installed), 'Client is not loaded from the installed package');
foreach (['tests', 'examples', '.sdkgen', 'consumer-test', 'vendor', 'phpunit.xml.dist'] as $excluded) {
    check(!file_exists("{$installed}/{$excluded}"), "the archive includes {$excluded}");
}
foreach (['src/Client.php', 'README.md', 'LICENSE', 'composer.json'] as $included) {
    check(file_exists("{$installed}/{$included}"), "the archive is missing {$included}");
}

$handler = new MockHandler([
    new Response(200, ['Content-Type' => 'application/json'], '{"ip":"8.8.8.8","network":{"asn":15169,"isp":"Google LLC","type":"DATACENTER"}}'),
    new Response(403, ['Content-Type' => 'application/problem+json'], '{"type":"about:blank","title":"Forbidden","status":403,"detail":"missing scope"}'),
    new Response(200, ['Content-Type' => 'application/x-ndjson'], "{\"ip\":\"203.0.113.7\",\"provider\":\"acme\"}\n"),
]);
$client = new Client(apiKey: 'consumer-key', httpClient: new GuzzleClient(['handler' => HandlerStack::create($handler)]));

$info = $client->lookupIp('8.8.8.8');
check($info->network?->asn === 15169, 'lookupIp did not decode the network');

try {
    $client->account();
    check(false, 'account did not throw');
} catch (ApiException $e) {
    check($e->statusCode === 403 && $e->detail === 'missing scope', 'the API error was not decoded');
}

$events = iterator_to_array($client->proxies->stream());
check(count($events) === 1 && $events[0]->provider === 'acme', 'the stream did not decode');

echo 'synthient-php ', Client::VERSION, ' works from ', $installed, "\n";
