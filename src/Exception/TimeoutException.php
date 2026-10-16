<?php

declare(strict_types=1);

namespace Synthient\Exception;

/**
 * The connection, the response, or a read from a stream or download took longer than the
 * client's timeout. On Guzzle 7, a stream or download that cannot connect in time throws a
 * ConnectionException instead, because Guzzle 7 reports it as a refused connection.
 */
final class TimeoutException extends ConnectionException {}
