<?php

declare(strict_types=1);

namespace Synthient\Exception;

/**
 * The connection, the response, or a read from a stream or download took longer than the
 * client's timeout.
 */
final class TimeoutException extends ConnectionException {}
