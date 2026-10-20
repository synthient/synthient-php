<?php

declare(strict_types=1);

namespace Synthient\Model;

use Synthient\Internal\Wire;

final readonly class ParquetSchema
{
    /**
     * @param list<ParquetColumn> $fields
     */
    public function __construct(
        #[Wire(list: ParquetColumn::class)]
        public array $fields,
    ) {}
}
