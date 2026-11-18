<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * The transport a Helios capture arrived over. The API may add values.
 */
final class HeliosTransport
{
    public const string TCP = 'tcp';
    public const string UDP = 'udp';
    public const string QUIC = 'quic';
}
