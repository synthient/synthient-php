<?php

declare(strict_types=1);

namespace Synthient\Event;

/**
 * A shell command captured by a Helios Android Debug Bridge honeypot.
 */
final readonly class HeliosAdbEvent
{
    /**
     * @param string $session the session identifier, 32 hex characters
     * @param int $sequentialId the command's 1-based position in its session
     * @param string $command the command as text; invalid UTF-8 becomes U+FFFD
     * @param string $hash the hex SHA-256 of the session's payload, shared by every command in the session
     */
    public function __construct(
        public string $session,
        public int $sequentialId,
        public string $command,
        public string $hash,
    ) {}
}
