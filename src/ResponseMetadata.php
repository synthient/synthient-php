<?php

declare(strict_types=1);

namespace Synthient;

/**
 * What the client's response hook receives for every API response, including retries,
 * export pages, stream handshakes, export redirects, and errors. Storage responses from
 * export downloads are excluded.
 */
final readonly class ResponseMetadata
{
    /**
     * @param string|null $requestId the X-Request-Id header, which support can trace
     * @param RateLimit|null $rateLimit null when the response has no complete RateLimit-* headers
     * @param string[][] $headers the response headers; an export redirect's
     *                                             Location is a presigned URL, so treat it as a secret
     */
    public function __construct(
        public int $statusCode,
        public ?string $requestId,
        public ?RateLimit $rateLimit,
        public array $headers,
    ) {}
}
