<?php

declare(strict_types=1);

namespace Synthient\Model;

final readonly class Organization
{
    /**
     * @param string $relation the account's role, see Relation
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $relation,
    ) {}
}
