<?php

declare(strict_types=1);

namespace Synthient;

use Generator;
use IteratorAggregate;
use LogicException;
use Psr\Http\Message\StreamInterface;
use Synthient\Exception\ConnectionException;
use Synthient\Exception\SynthientException;
use Synthient\Exception\TimeoutException;
use Synthient\Internal\Decoder;

/**
 * An open connection to a real-time feed, from Feed::stream().
 *
 * Iterate it once to receive events as they arrive. The server ends a stream after up to 30
 * minutes, and iteration then stops without an error, so reconnect in a loop. Events sent
 * between connections are missed. Call close() when you stop early, ideally in a finally
 * block, so the connection is not held until garbage collection.
 *
 * @template T of object
 *
 * @implements IteratorAggregate<int, T>
 */
final class EventStream implements IteratorAggregate
{
    public const int MAX_EVENT_SIZE = 16 << 20;

    private bool $iterated = false;
    private bool $closed = false;

    /**
     * @internal
     *
     * @param class-string<T> $event
     */
    public function __construct(
        private readonly StreamInterface $body,
        private readonly string $event,
    ) {}

    /**
     * @return Generator<int, T>
     *
     * @throws ConnectionException when a read fails
     * @throws TimeoutException when a read times out
     * @throws SynthientException when an event is malformed
     */
    public function getIterator(): Generator
    {
        if ($this->iterated) {
            throw new LogicException('an event stream can only be iterated once');
        }
        $this->iterated = true;

        try {
            $buffer = '';
            while (!$this->closed) {
                if ($this->body->eof()) {
                    $line = trim($buffer);
                    if ($line !== '') {
                        yield Decoder::decode($this->event, $line);
                    }
                    break;
                }
                $chunk = $this->body->read(65536);
                $buffer .= $chunk;
                if (str_contains($chunk, "\n")) {
                    $lines = explode("\n", $buffer);
                    $buffer = array_pop($lines);
                    foreach ($lines as $line) {
                        if (\strlen($line) > self::MAX_EVENT_SIZE) {
                            throw new SynthientException('synthient: stream event exceeds ' . self::MAX_EVENT_SIZE . ' bytes');
                        }
                        $line = trim($line);
                        if ($line !== '') {
                            yield Decoder::decode($this->event, $line);
                        }
                    }
                }
                if (\strlen($buffer) > self::MAX_EVENT_SIZE) {
                    throw new SynthientException('synthient: stream event exceeds ' . self::MAX_EVENT_SIZE . ' bytes');
                }
            }
        } finally {
            $this->close();
        }
    }

    /**
     * Closes the connection. An iteration in progress then ends without an error, after the
     * events already received.
     */
    public function close(): void
    {
        $this->closed = true;
        $this->body->close();
    }
}
