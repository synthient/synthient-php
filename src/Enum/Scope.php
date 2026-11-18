<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * BASIC authorizes lookups and account details. The API may add values.
 */
final class Scope
{
    public const string BASIC = 'BASIC';
    public const string PROXY_FEEDS = 'PROXY_FEEDS';
    public const string PROXY_FIREHOSE = 'PROXY_FIREHOSE';
    public const string ANONYMIZERS_FEED = 'ANONYMIZERS_FEED';
    public const string ANONYMIZERS_STREAM = 'ANONYMIZERS_STREAM';
    public const string JA3_FEED = 'JA3_FEED';
    public const string JA3_STREAM = 'JA3_STREAM';
    public const string TORRENTS_FEED = 'TORRENTS_FEED';
    public const string TORRENTS_STREAM = 'TORRENTS_STREAM';
    public const string HONEYPOT_HTTP_FEED = 'HONEYPOT_HTTP_FEED';
    public const string HONEYPOT_HTTP_STREAM = 'HONEYPOT_HTTP_STREAM';
    public const string HONEYPOT_HTTPS_FEED = 'HONEYPOT_HTTPS_FEED';
    public const string HONEYPOT_HTTPS_STREAM = 'HONEYPOT_HTTPS_STREAM';
    public const string HONEYPOT_ADB_FEED = 'HONEYPOT_ADB_FEED';
    public const string HONEYPOT_ADB_STREAM = 'HONEYPOT_ADB_STREAM';
    public const string HONEYPOT_DNS_FEED = 'HONEYPOT_DNS_FEED';
    public const string HONEYPOT_DNS_STREAM = 'HONEYPOT_DNS_STREAM';
    public const string JA4_FEED = 'JA4_FEED';
    public const string JA4_STREAM = 'JA4_STREAM';
    public const string JA4T_FEED = 'JA4T_FEED';
    public const string JA4T_STREAM = 'JA4T_STREAM';

    /** A dashboard permission that authorizes no API operation. */
    public const string HELIOS = 'HELIOS';

    public const string WHOIS_FEED = 'WHOIS_FEED';

    /** Reserved: no operation accepts it yet. */
    public const string WHOIS_STREAM = 'WHOIS_STREAM';

    public const string GEOLOCATION_FEED = 'GEOLOCATION_FEED';

    /** Reserved for an upcoming network IOC feed. */
    public const string NETWORK_IOCS_FEED = 'NETWORK_IOCS_FEED';

    /** Reserved for an upcoming network IOC feed. */
    public const string NETWORK_IOCS_STREAM = 'NETWORK_IOCS_STREAM';

    /** Reserved for an upcoming file IOC feed. */
    public const string FILE_IOCS_FEED = 'FILE_IOCS_FEED';

    /** Reserved for an upcoming file IOC feed. */
    public const string FILE_IOCS_STREAM = 'FILE_IOCS_STREAM';

    /**
     * @deprecated It never authorized a public operation, and the API no longer lists it. It
     *             will be removed in the next major version.
     */
    public const string ADMIN_PROXIES = 'ADMIN_PROXIES';
}
