<?php

declare(strict_types=1);

namespace Synthient\Tests;

use DateTimeImmutable;

/**
 * Response bodies shaped like the API's, which omits optional fields rather than sending
 * null. ipInfo() carries unknown fields to prove additive fields are ignored.
 */
final class Fixtures
{
    /**
     * @return array<string, mixed>
     */
    public static function ipInfo(string $ip = '8.8.8.8'): array
    {
        return [
            'ip' => $ip,
            'network' => [
                'asn' => 15169,
                'isp' => 'Google LLC',
                'type' => 'DATACENTER',
                'future_field' => true,
            ],
            'location' => [
                'country' => 'US',
                'state' => 'California',
                'city' => 'Mountain View',
                'timezone' => 'America/Los_Angeles',
                'latitude' => 37,
                'longitude' => -122.0838,
                'geo_hash' => '9q9hv',
            ],
            'intelligence' => [
                'risk_score' => 12,
                'categories' => ['DATACENTER_PROXY'],
                'behavior' => [],
                'providers' => [['provider' => 'acme', 'type' => 'DATACENTER_PROXY', 'last_seen' => 1_760_000_000]],
                'devices' => [['os' => 'Linux', 'version' => '6.1', 'last_seen' => 1_760_000_000]],
            ],
            'future_field' => 'ignored',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function account(): array
    {
        return [
            'email' => 'dev@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'organization' => ['id' => '7d1f3c2a-0000-4000-8000-000000000001', 'name' => 'Example', 'relation' => 'OWNER'],
            'scopes' => ['BASIC', 'PROXY_FEEDS'],
            'lookup_quota' => ['credits' => 9_000_000_000, 'resets_in' => 3600],
            'api_key' => ['id' => '7d1f3c2a-0000-4000-8000-000000000002', 'name' => 'sdk', 'created_at' => '2026-09-28T19:04:52.463869Z'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function domain(): array
    {
        return [
            'domain' => 'example.com',
            'status' => 'active',
            'stats' => ['events_24h' => 5, 'total_events_30d' => 50],
            'unique_ips' => ['value_24h' => 2, 'value_30d' => 20, 'sparkline_24h' => [0, 1, 1]],
            'time_series' => [['date' => 1_759_968_000, 'events' => 5, 'unique_ips' => 2]],
            'top_asn' => ['asn' => 4_294_967_295, 'events' => 3],
            'top_subdomains' => [['subdomain' => 'www.example.com', 'count' => 4]],
            'top_ports' => [['port' => 443, 'count' => 4]],
            'geo_distribution' => [['country_code' => 'US', 'unique_ips' => 2, 'events' => 5]],
            'hour_dow_heatmap' => array_fill(0, 7, array_fill(0, 24, 0)),
            'activity_stats' => [
                'cadence' => 'Activity is burst-driven with significant peaks.',
                'median_per_hour' => 0,
                'p95_per_hour' => 2,
                'peak_hour' => 14,
                'quiet_hour' => 3,
            ],
            'recent_events' => [[
                'timestamp' => 1_760_000_000,
                'source_ip_masked' => '203.0.113.7',
                'target_subdomain' => 'www.example.com',
                'port' => 443,
                'country_code' => 'US',
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function export(string $id = '2026-05-03'): array
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}/', $id, $match) === 1 ? $match[0] : '2026-05-03';
        $hour = str_contains($id, '/') ? (int) explode('/', $id)[1] : null;
        $export = [
            'id' => $id,
            'kind' => $hour === null ? 'daily' : 'hourly',
            'date' => $date,
            'size_bytes' => 1024,
            'row_count' => 10,
            'checksum' => str_repeat('ab', 32),
            'created_at' => (new DateTimeImmutable("{$date}T00:00:00Z"))->getTimestamp() + ($hour === null ? 86400 : ($hour + 1) * 3600),
            'download_path' => "/api/v4/feeds/proxies/export/{$id}",
        ];
        if ($hour !== null) {
            $export['hour'] = $hour;
        }

        return $export;
    }

    /**
     * @param list<array<string, mixed>> $exports
     *
     * @return array<string, mixed>
     */
    public static function exportPage(array $exports, ?string $nextCursor = null): array
    {
        $page = ['stream' => 'proxies', 'feeds' => $exports];
        if ($nextCursor !== null) {
            $page['next_cursor'] = $nextCursor;
        }

        return $page;
    }

    /**
     * @return array<string, mixed>
     */
    public static function exportMeta(): array
    {
        return [
            'id' => '2026-05-03/7',
            'stream' => 'proxies',
            'kind' => 'hourly',
            'format' => 'parquet',
            'date' => 1_777_791_600,
            'hour' => 7,
            'rows' => 10,
            'size' => 1024,
            'checksum' => str_repeat('ab', 32),
            'created_at' => 1_777_795_200,
            'schema' => ['fields' => [['name' => 'ip', 'type' => 'string'], ['name' => 'asn', 'type' => 'uint32']]],
        ];
    }
}
