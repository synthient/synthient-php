<?php

declare(strict_types=1);

namespace Synthient\Exception;

/**
 * The request could not be completed because of a network failure. A billed lookup that
 * fails this way may or may not have been charged, so it is never retried automatically.
 */
class ConnectionException extends SynthientException {}
