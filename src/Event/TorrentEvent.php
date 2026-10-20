<?php

declare(strict_types=1);

namespace Synthient\Event;

use DateTimeImmutable;
use Synthient\Internal\Wire;

/**
 * Every field is null when the API omits it.
 */
final readonly class TorrentEvent
{
    /**
     * @param string|null $infoHash the hex SHA-1 info hash
     * @param int|null $totalSize the total size of the files in bytes
     * @param list<TorrentFile>|null $files
     * @param list<TorrentPeer>|null $peers
     */
    public function __construct(
        public ?string $infoHash = null,
        public ?string $name = null,
        public ?string $magnetUri = null,
        public ?int $totalSize = null,
        public ?int $pieceLength = null,
        public ?int $fileCount = null,
        #[Wire(list: TorrentFile::class)]
        public ?array $files = null,
        #[Wire(list: TorrentPeer::class)]
        public ?array $peers = null,
        public ?DateTimeImmutable $timestamp = null,
    ) {}
}
