<?php

declare(strict_types=1);

namespace Synthient\Event;

use Synthient\Internal\Wire;

/**
 * The parsed request line and headers of a captured HTTP request.
 */
final readonly class HttpRequestDetails
{
    /**
     * @param array<string, string>|null $headers
     */
    public function __construct(
        public ?string $method = null,
        public ?string $uri = null,
        public ?string $version = null,
        #[Wire(map: 'string')]
        public ?array $headers = null,
    ) {}
}
