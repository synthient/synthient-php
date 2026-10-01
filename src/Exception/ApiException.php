<?php

declare(strict_types=1);

namespace Synthient\Exception;

use GuzzleHttp\Psr7\Response;

/**
 * The API, or the storage behind an export download, answered with an error status.
 *
 * The message holds only the status, so it is safe to log. The other properties carry
 * untrusted, possibly sensitive server data: title, detail, errors, and headers.
 *
 * Common statuses: 401 for a missing or invalid key, 402 when the team is out of lookup
 * credits, 403 when the key lacks a scope, 404 while a daily export is still rolling up,
 * 422 for invalid input, and 429 when rate limited.
 */
final class ApiException extends SynthientException
{
    /**
     * @param string|null $type the problem type URI
     * @param string|null $detail the problem detail, or the body of a text/plain error
     * @param array<string, list<string>> $errors validation errors by field, from a 422
     * @param string|null $requestId the X-Request-Id header, or the problem's instance
     * @param int|null $retryAfter seconds from the Retry-After header
     * @param string[][] $headers the response headers
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly ?string $type = null,
        public readonly ?string $title = null,
        public readonly ?string $detail = null,
        public readonly array $errors = [],
        public readonly ?string $requestId = null,
        public readonly ?int $retryAfter = null,
        public readonly array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(self::statusLine($statusCode), $statusCode, $previous);
    }

    private static function statusLine(int $status): string
    {
        $phrase = $status >= 100 && $status < 600 ? (new Response($status))->getReasonPhrase() : '';

        return rtrim("synthient: {$status} {$phrase}");
    }
}
