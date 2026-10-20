<?php

declare(strict_types=1);

namespace Synthient\Model;

final readonly class ParquetColumn
{
    /**
     * @param string $type see ParquetType
     */
    public function __construct(
        public string $name,
        public string $type,
    ) {}
}
