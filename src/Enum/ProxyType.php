<?php

declare(strict_types=1);

namespace Synthient\Enum;

/**
 * The kind of proxy, VPN, or automated service behind an address in a feed. The API may add values.
 */
final class ProxyType
{
    public const string FREE_VPN = 'FREE_VPN';
    public const string COMMERCIAL_VPN = 'COMMERCIAL_VPN';
    public const string ENTERPRISE_VPN = 'ENTERPRISE_VPN';
    public const string MOBILE_PROXY = 'MOBILE_PROXY';
    public const string BLOCKCHAIN_PROXY = 'BLOCKCHAIN_PROXY';
    public const string RESIDENTIAL_PROXY = 'RESIDENTIAL_PROXY';
    public const string PUBLIC_PROXY = 'PUBLIC_PROXY';
    public const string DATACENTER_PROXY = 'DATACENTER_PROXY';
    public const string TOR_NODE = 'TOR_NODE';
    public const string PRIVATE_RELAY = 'PRIVATE_RELAY';
    public const string BOTNET = 'BOTNET';
    public const string SEARCH_ENGINE = 'SEARCH_ENGINE';
    public const string AI_CRAWLER = 'AI_CRAWLER';
    public const string SOCIAL_MEDIA = 'SOCIAL_MEDIA';
    public const string UPTIME_MONITOR = 'UPTIME_MONITOR';
    public const string LINK_PREVIEW = 'LINK_PREVIEW';
    public const string SEO_CRAWLER = 'SEO_CRAWLER';
    public const string WEB_ARCHIVER = 'WEB_ARCHIVER';
    public const string WEBHOOK_PROVIDER = 'WEBHOOK_PROVIDER';
    public const string PAYMENT_PROCESSOR = 'PAYMENT_PROCESSOR';
    public const string INTERNET_SCANNER = 'INTERNET_SCANNER';
}
