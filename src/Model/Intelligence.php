<?php

declare(strict_types=1);

namespace Synthient\Model;

use Synthient\Internal\Wire;

final readonly class Intelligence
{
    /**
     * @param int $riskScore from 0 (no risk) to 100
     * @param list<string> $categories categories such as RESIDENTIAL_PROXY; the API may add values
     * @param list<string> $behavior
     * @param list<Provider> $providers the proxy and VPN providers seen using the address
     * @param list<Device> $devices the devices seen behind the address
     */
    public function __construct(
        public int $riskScore,
        #[Wire(list: 'string')]
        public array $categories,
        #[Wire(list: 'string')]
        public array $behavior,
        #[Wire(list: Provider::class)]
        public array $providers,
        #[Wire(list: Device::class)]
        public array $devices,
    ) {}
}
