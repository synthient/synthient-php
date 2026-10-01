<?php

declare(strict_types=1);

namespace Synthient\Exception;

use RuntimeException;

/**
 * The base class of every error the SDK throws at runtime: API errors, transport failures,
 * and malformed responses. Invalid arguments throw \InvalidArgumentException instead.
 */
class SynthientException extends RuntimeException {}
