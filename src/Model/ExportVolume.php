<?php

declare(strict_types=1);

namespace Synthient\Model;

use Synthient\Internal\Wire;

/**
 * The rows and bytes exported per UTC day, from exportVolume().
 */
final readonly class ExportVolume
{
    /**
     * @param list<ExportVolumeDay> $days oldest first, including today; days without exports are absent
     */
    public function __construct(
        public string $stream,
        #[Wire(list: ExportVolumeDay::class)]
        public array $days,
    ) {}
}
