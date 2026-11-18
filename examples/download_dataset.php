<?php

/*
 * Downloads the newest geolocation export as a MaxMind DB to geolocation.mmdb and verifies its
 * checksum. Needs the GEOLOCATION_FEED scope.
 *
 *     SYNTHIENT_API_KEY=... php examples/download_dataset.php
 */

declare(strict_types=1);

use Synthient\Enum\ExportFormat;

require __DIR__ . '/../vendor/autoload.php';

function fail(string $message): never
{
    fwrite(STDERR, "{$message}\n");
    exit(1);
}

$client = new Synthient\Client();

$export = $client->geolocation->listExports(limit: 1)->exports[0] ?? fail('no geolocation export found');
$mmdb = null;
foreach ($export->formats as $file) {
    if ($file->format === ExportFormat::MMDB) {
        $mmdb = $file;
    }
}
if ($mmdb === null) {
    fail("the {$export->date} export has no mmdb file");
}

// The listing can start with "latest", which moves when a new day is published.
$body = $client->geolocation->download($export->date, format: ExportFormat::MMDB);
$part = 'geolocation.mmdb.part';
$out = fopen($part, 'wb') ?: fail("cannot open {$part}");
$hash = hash_init('sha256');
$verified = false;
try {
    while (!$body->eof()) {
        $chunk = $body->read(1 << 20);
        if (fwrite($out, $chunk) !== strlen($chunk)) {
            throw new RuntimeException("cannot write {$part}");
        }
        hash_update($hash, $chunk);
    }
    $verified = hash_equals($mmdb->checksum, hash_final($hash));
} finally {
    $body->close();
    fclose($out);
    if (!$verified) {
        unlink($part);
    }
}

if (!$verified) {
    fail('checksum mismatch');
}
if (!rename($part, 'geolocation.mmdb')) {
    fail('cannot rename the download to geolocation.mmdb');
}
echo "wrote geolocation.mmdb ({$export->date}, {$mmdb->sizeBytes} bytes)\n";
