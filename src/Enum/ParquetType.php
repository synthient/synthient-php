<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * The API may add values.
 */
final class ParquetType
{
    public const string STRING = 'string';
    public const string INT64 = 'int64';
    public const string UINT32 = 'uint32';
    public const string UINT64 = 'uint64';
    public const string BOOL = 'bool';
    public const string BYTES = 'bytes';
}
