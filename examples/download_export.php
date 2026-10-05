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

function fail(string $message): never
{
    fwrite(STDERR, "{$message}\n");
    exit(1);
}

$client = new Synthient\Client();

$export = null;
foreach ($client->proxies->allExports() as $candidate) {
    if ($candidate->kind === ExportKind::DAILY) {
        $export = $candidate;

        break;
    }
}
if ($export === null) {
    fail('no daily export found');
}

$body = $client->proxies->download($export->id);
$part = 'proxies.parquet.part';
$file = fopen($part, 'wb') ?: fail("cannot open {$part}");
$hash = hash_init('sha256');
$verified = false;
try {
    while (!$body->eof()) {
        $chunk = $body->read(1 << 20);
        if (fwrite($file, $chunk) !== strlen($chunk)) {
            throw new RuntimeException("cannot write {$part}");
        }
        hash_update($hash, $chunk);
    }
    $verified = hash_equals($export->checksum, hash_final($hash));
} finally {
    $body->close();
    fclose($file);
    if (!$verified) {
        unlink($part);
    }
}

if (!$verified) {
    fail('checksum mismatch');
}
if (!rename($part, 'proxies.parquet')) {
    fail('cannot rename the download to proxies.parquet');
}
echo "wrote proxies.parquet ({$export->date}, {$export->rowCount} rows, {$export->sizeBytes} bytes)\n";
