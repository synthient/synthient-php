<?php

declare(strict_types=1);

namespace Synthient\Event;

/**
 * A TLS extension in a ClientHello.
 */
final readonly class TlsExtension
{
    /**
     * @param int|null $length the extension body length in bytes
     */
    public function __construct(
        public ?int $code = null,
        public ?string $name = null,
        public ?int $length = null,
    ) {}
}
