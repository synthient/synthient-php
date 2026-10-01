<?php

/*
 * Prints the account that owns the API key and its remaining lookup credits.
 *
 *     SYNTHIENT_API_KEY=... php examples/account.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$client = new Synthient\Client();
$account = $client->account();

echo "{$account->firstName} {$account->lastName} <{$account->email}>\n";
echo "organization: {$account->organization->name} ({$account->organization->relation})\n";
echo 'scopes: ', implode(', ', $account->scopes), "\n";
echo "credits: {$account->lookupQuota->credits}, reset in {$account->lookupQuota->resetsIn} seconds\n";
