<?php

declare(strict_types=1);

/*
 * A scripted HTTP/1.1 server for transport tests. Each accepted connection runs the next
 * script from the JSON file in argv[1] in a forked child, and every request read is logged
 * as a JSON line to argv[2]. The listening port is printed on the first line of stdout.
 *
 * Steps: {"read": true} reads one request, {"write": "..."} sends raw bytes,
 * {"sleep": seconds} pauses, and {"close": true} ends the connection. After the last step
 * the child waits for the peer to close.
 */

posix_setpgid(0, 0);

[, $script, $log] = $_SERVER['argv'];
$connections = json_decode((string) file_get_contents($script), true, 512, JSON_THROW_ON_ERROR);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) {
    fwrite(STDERR, "listen failed: {$error}\n");
    exit(1);
}
$name = (string) stream_socket_get_name($server, false);
echo substr($name, strrpos($name, ':') + 1), "\n";

foreach ($connections as $index => $steps) {
    $socket = stream_socket_accept($server, 30);
    if ($socket === false) {
        break;
    }
    if (pcntl_fork() !== 0) {
        fclose($socket);

        continue;
    }
    stream_set_timeout($socket, 10);
    foreach ($steps as $step) {
        if (isset($step['read'])) {
            $head = '';
            while (!str_contains($head, "\r\n\r\n")) {
                $line = fgets($socket);
                if ($line === false) {
                    exit(0);
                }
                $head .= $line;
            }
            $lines = explode("\r\n", trim($head));
            $headers = [];
            foreach (array_slice($lines, 1) as $header) {
                [$key, $value] = explode(':', $header, 2);
                $headers[strtolower($key)] = trim($value);
            }
            $length = (int) ($headers['content-length'] ?? 0);
            $body = $length > 0 ? (string) stream_get_contents($socket, $length) : '';
            file_put_contents($log, json_encode(['connection' => $index, 'line' => $lines[0], 'headers' => $headers, 'body' => $body]) . "\n", FILE_APPEND | LOCK_EX);
        } elseif (isset($step['write'])) {
            fwrite($socket, $step['write']);
        } elseif (isset($step['sleep'])) {
            usleep((int) ($step['sleep'] * 1_000_000));
        } elseif (isset($step['close'])) {
            fclose($socket);
            exit(0);
        }
    }
    while (!feof($socket) && fread($socket, 8192) !== false) {
        if (stream_get_meta_data($socket)['timed_out']) {
            break;
        }
    }
    exit(0);
}
while (pcntl_wait($status) > 0) {
}
