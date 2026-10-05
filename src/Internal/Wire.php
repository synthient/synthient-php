<?php

declare(strict_types=1);

namespace Synthient\Internal;

use Attribute;

/**
 * Describes how a model property appears in JSON when its native type is not enough.
 *
 * @internal
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Wire
{
    /**
     * @param string|null $name the JSON name, when it is not the snake_case form of the property
     * @param string|null $list the element type of a list: string, int, a class, or a nested list<...>
     * @param string|null $map the value type of a JSON object read into an array keyed by name
     * @param string|null $type a type the native one cannot express: uint64, read into a string
     */
    public function __construct(
        public ?string $name = null,
        public ?string $list = null,
        public ?string $map = null,
        public ?string $type = null,
    ) {}
}
