<?php

declare(strict_types=1);

namespace Synthient\Event;

/**
 * A TLS code point and its name. GREASE values are named GREASE(0x....).
 */
final readonly class TlsCodeName
{
    public function __construct(
        public ?int $code = null,
        public ?string $name = null,
    ) {}
}
