<?php

declare(strict_types=1);

namespace Synthient\Event;

use Synthient\Internal\Wire;

final readonly class HttpRequestDetails
{
    /**
     * @param array<array-key, string>|null $headers by name; PHP stores a numeric name such as
     *                                      "123" as an int key
     */
    public function __construct(
        public ?string $method = null,
        public ?string $uri = null,
        public ?string $version = null,
        #[Wire(type: 'map<string>')]
        public ?array $headers = null,
    ) {}
}
