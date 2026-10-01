<?php

declare(strict_types=1);

namespace Synthient;

use Generator;
use IteratorAggregate;
use LogicException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Synthient\Exception\ConnectionException;
use Synthient\Exception\SynthientException;
use Synthient\Exception\TimeoutException;
use Synthient\Internal\Decoder;

/**
 * An open connection to a real-time feed, from Feed::stream().
 *
 * Iterate it once to receive events as they arrive. The server ends a stream after about 30
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
     * @throws SynthientException when the connection fails or an event is malformed
     */
    public function getIterator(): Generator
    {
        if ($this->iterated) {
            throw new LogicException('an event stream can only be iterated once');
        }
        $this->iterated = true;

        try {
            $buffer = '';
            while (!$this->body->eof()) {
                try {
                    $chunk = $this->body->read(65536);
                } catch (RuntimeException $e) {
                    throw $this->body->getMetadata('timed_out') === true
                        ? new TimeoutException('synthient: stream read timed out', 0, $e)
                        : new ConnectionException('synthient: stream read failed', 0, $e);
                }
                if ($chunk === '' && $this->body->getMetadata('timed_out') === true) {
                    throw new TimeoutException('synthient: stream read timed out');
                }
                $lines = explode("\n", $buffer . $chunk);
                $buffer = array_pop($lines);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        yield Decoder::decode($this->event, $line);
                    }
                }
                if (\strlen($buffer) > self::MAX_EVENT_SIZE) {
                    throw new SynthientException('synthient: stream event exceeds ' . self::MAX_EVENT_SIZE . ' bytes');
                }
            }
            $line = trim($buffer);
            if ($line !== '') {
                yield Decoder::decode($this->event, $line);
            }
        } finally {
            $this->close();
        }
    }

    public function close(): void
    {
        $this->body->close();
    }
}
