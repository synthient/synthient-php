<?php

declare(strict_types=1);

namespace Synthient\Tests\Server;

use RuntimeException;
use Throwable;

/**
 * Runs server.php in a child process for tests that need real connections.
 */
final class ScriptedServer
{
    public readonly int $port;

    /** @var resource|null */
    private $process;

    private readonly string $log;

    /**
     * @param list<list<array<string, mixed>>> $connections the steps of each connection, in accept order
     */
    public function __construct(array $connections)
    {
        $script = (string) tempnam(sys_get_temp_dir(), 'synthient-script');
        $this->log = (string) tempnam(sys_get_temp_dir(), 'synthient-log');
        try {
            file_put_contents($script, json_encode($connections, JSON_THROW_ON_ERROR));
            $process = proc_open([PHP_BINARY, __DIR__ . '/server.php', $script, $this->log], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if ($process === false) {
                throw new RuntimeException('could not start the test server');
            }
            $this->process = $process;
            $port = fgets($pipes[1]);
            if ($port === false) {
                throw new RuntimeException('the test server did not start: ' . stream_get_contents($pipes[2]));
            }
            $this->port = (int) $port;
        } catch (Throwable $e) {
            $this->stop();

            throw $e;
        } finally {
            // The server reads its script before it prints the port.
            @unlink($script);
        }
    }

    public function __destruct()
    {
        $this->stop();
    }

    public function url(string $path = ''): string
    {
        return "http://127.0.0.1:{$this->port}{$path}";
    }

    /**
     * @return list<array{connection: int, line: string, headers: array<string, string>, body: string}>
     */
    public function requests(): array
    {
        $requests = [];
        foreach (file($this->log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            /** @var array{connection: int, line: string, headers: array<string, string>, body: string} $request */
            $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $requests[] = $request;
        }

        return $requests;
    }

    /**
     * @param array<string, string> $headers
     */
    public static function response(int $status, string $body, array $headers = []): string
    {
        $head = "HTTP/1.1 {$status} Status\r\nContent-Length: " . \strlen($body) . "\r\n";
        foreach (['Content-Type' => 'application/json', ...$headers] as $name => $value) {
            $head .= "{$name}: {$value}\r\n";
        }

        return "{$head}\r\n{$body}";
    }

    /**
     * @param array<string, string> $headers
     */
    public static function json(int $status, mixed $body, array $headers = []): string
    {
        return self::response($status, json_encode($body, JSON_THROW_ON_ERROR), $headers);
    }

    public static function chunk(string $data): string
    {
        return dechex(\strlen($data)) . "\r\n{$data}\r\n";
    }

    private function stop(): void
    {
        if ($this->process !== null) {
            posix_kill(-proc_get_status($this->process)['pid'], SIGKILL);
            proc_close($this->process);
            $this->process = null;
        }
        @unlink($this->log);
    }
}
