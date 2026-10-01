<?php

/*
 * Downloads the newest daily proxies export to proxies.parquet and verifies its checksum.
 * Needs the PROXY_FEEDS scope.
 *
 *     SYNTHIENT_API_KEY=... php examples/download_export.php
 */

declare(strict_types=1);

use Synthient\Enum\ExportKind;

require __DIR__ . '/../vendor/autoload.php';

$client = new Synthient\Client();

$export = null;
foreach ($client->proxies->allExports() as $candidate) {
    if ($candidate->kind === ExportKind::DAILY) {
        $export = $candidate;

        break;
    }
}
if ($export === null) {
    exit("no daily export found\n");
}

$body = $client->proxies->download($export->id);
$file = fopen('proxies.parquet', 'wb') ?: exit("cannot open proxies.parquet\n");
$hash = hash_init('sha256');
try {
    while (!$body->eof()) {
        $chunk = $body->read(1 << 20);
        hash_update($hash, $chunk);
        fwrite($file, $chunk);
    }
} finally {
    $body->close();
    fclose($file);
}

if (!hash_equals($export->checksum, hash_final($hash))) {
    unlink('proxies.parquet');
    exit("checksum mismatch\n");
}
echo "wrote proxies.parquet ({$export->date}, {$export->rowCount} rows, {$export->sizeBytes} bytes)\n";
