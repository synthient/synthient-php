<?php

declare(strict_types=1);

namespace Synthient\Event;

use Synthient\Internal\Wire;

/**
 * A parsed TLS ClientHello. Fields the sensor could not parse are null.
 */
final readonly class TlsClientHello
{
    /**
     * @param string|null $clientRandom hex-encoded
     * @param string|null $sessionId hex-encoded
     * @param list<TlsCodeName>|null $cipherSuites
     * @param list<int>|null $compressionMethods
     * @param list<TlsExtension>|null $extensions in wire order
     * @param list<string>|null $alpn
     * @param list<string>|null $supportedVersions
     * @param list<TlsCodeName>|null $supportedGroups
     * @param list<TlsCodeName>|null $signatureAlgorithms
     * @param list<string>|null $ecPointFormats
     * @param list<string>|null $keyShareGroups
     * @param list<string>|null $pskKeyExchangeModes
     * @param list<string>|null $compressCertificate
     */
    public function __construct(
        public ?string $recordVersion = null,
        public ?string $handshakeVersion = null,
        public ?string $clientRandom = null,
        public ?string $sessionId = null,
        public ?int $sessionIdLength = null,
        #[Wire(list: TlsCodeName::class)]
        public ?array $cipherSuites = null,
        #[Wire(list: 'int')]
        public ?array $compressionMethods = null,
        #[Wire(list: TlsExtension::class)]
        public ?array $extensions = null,
        public ?string $sni = null,
        #[Wire(list: 'string')]
        public ?array $alpn = null,
        #[Wire(list: 'string')]
        public ?array $supportedVersions = null,
        #[Wire(list: TlsCodeName::class)]
        public ?array $supportedGroups = null,
        #[Wire(list: TlsCodeName::class)]
        public ?array $signatureAlgorithms = null,
        #[Wire(list: 'string')]
        public ?array $ecPointFormats = null,
        #[Wire(list: 'string')]
        public ?array $keyShareGroups = null,
        #[Wire(list: 'string')]
        public ?array $pskKeyExchangeModes = null,
        #[Wire(list: 'string')]
        public ?array $compressCertificate = null,
        public ?bool $hasGrease = null,
        public ?bool $applicationSettings = null,
        public ?bool $delegatedCredentials = null,
        public ?bool $encryptThenMac = null,
        public ?bool $extendedMasterSecret = null,
        public ?bool $postHandshakeAuth = null,
        public ?bool $renegotiationInfo = null,
        public ?bool $signedCertificateTimestamps = null,
        public ?bool $statusRequest = null,
    ) {}
}
