<?php

declare(strict_types=1);

namespace Synthient\Model;

/**
 * The network an address belongs to.
 */
final readonly class Network
{
    /**
     * @param int $asn the autonomous system number, or 0 if the address is not routed
     * @param string $type the kind of network, see NetworkType
     */
    public function __construct(
        public int $asn,
        public string $isp,
        public string $type,
        public ?string $org = null,
        public ?string $domain = null,
        public ?string $abuseEmail = null,
        public ?string $abusePhone = null,
    ) {}
}
