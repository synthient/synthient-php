<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * The operating system of a device seen behind an address. The API may add values.
 */
final class OperatingSystem
{
    public const string ANDROID = 'Android';
    public const string CHROME_OS = 'Chrome OS';
    public const string FREEBSD = 'FreeBSD';
    public const string IOS = 'iOS';
    public const string LINUX = 'Linux';
    public const string MAC_OS_X = 'Mac OS X';
    public const string WINDOWS = 'Windows';
}
