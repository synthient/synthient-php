<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * A permission granted to an API key. BASIC authorizes lookups and account details. The API may add values.
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
    public const string ADMIN_PROXIES = 'ADMIN_PROXIES';
}
