<?php

declare(strict_types=1);

namespace Synthient\Model;

use Synthient\Internal\Wire;

/**
 * One page of exports, newest first.
 */
final readonly class ExportPage
{
    /**
     * @param list<Export> $exports
     * @param string|null $nextCursor the cursor for the next page, or null or empty on the last page
     */
    public function __construct(
        public string $stream,
        #[Wire(name: 'feeds', list: Export::class)]
        public array $exports,
        public ?string $nextCursor = null,
    ) {}
}
