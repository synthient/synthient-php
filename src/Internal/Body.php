<?php

declare(strict_types=1);

namespace Synthient\Internal;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Synthient\Exception\ConnectionException;
use Synthient\Exception\TimeoutException;

/**
 * A response body whose failed reads throw ConnectionException, or TimeoutException when the
 * read timed out.
 *
 * @internal
 */
final class Body implements StreamInterface
{
    use StreamDecoratorTrait;

    public function __construct(private StreamInterface $stream) {}

    public function read(int $length): string
    {
        try {
            $chunk = $this->stream->read($length);
        } catch (RuntimeException $e) {
            throw $this->timedOut()
                ? new TimeoutException('synthient: reading the response timed out', 0, $e)
                : new ConnectionException('synthient: reading the response failed', 0, $e);
        }
        if ($chunk === '' && $this->timedOut()) {
            throw new TimeoutException('synthient: reading the response timed out');
        }

        return $chunk;
    }

    private function timedOut(): bool
    {
        return $this->stream->getMetadata('timed_out') === true;
    }
}
