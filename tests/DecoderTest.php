<?php

declare(strict_types=1);

namespace Synthient\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Synthient\Event\HeliosDnsEvent;
use Synthient\Event\HttpRequestDetails;
use Synthient\Exception\SynthientException;
use Synthient\Internal\Decoder;
use Synthient\Model\Account;
use Synthient\Model\IpInfo;

final class DecoderTest extends TestCase
{
    /**
     * @return iterable<string, array{callable(array<string, mixed>): array<string, mixed>, string}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'missing required field' => [static function (array $body): array {
            unset($body['intelligence']['risk_score']);

            return $body;
        }, 'intelligence.risk_score is missing'];
        yield 'null required field' => [static fn(array $body): array => ['ip' => null] + $body, 'ip is missing'];
        yield 'numeric string for an integer' => [static function (array $body): array {
            $body['network']['asn'] = '15169';

            return $body;
        }, 'network.asn must be an integer'];
        yield 'boolean for an integer' => [static function (array $body): array {
            $body['intelligence']['risk_score'] = true;

            return $body;
        }, 'intelligence.risk_score must be an integer'];
        yield 'integer for a string' => [static fn(array $body): array => ['ip' => 1] + $body, 'ip must be a string'];
        yield 'string for a number' => [static function (array $body): array {
            $body['location']['latitude'] = '37.4';

            return $body;
        }, 'location.latitude must be a number'];
        yield 'list for an object' => [static fn(array $body): array => ['network' => [1, 2]] + $body, 'network must be an object'];
        yield 'object for a list' => [static function (array $body): array {
            $body['intelligence']['categories'] = ['a' => 'b'];

            return $body;
        }, 'intelligence.categories must be a list'];
        yield 'bad list element' => [static function (array $body): array {
            $body['intelligence']['categories'] = ['ok', 7];

            return $body;
        }, 'intelligence.categories[1] must be a string'];
        yield 'bad nested object' => [static function (array $body): array {
            $body['intelligence']['devices'][0]['last_seen'] = 'yesterday';

            return $body;
        }, 'intelligence.devices[0].last_seen must be a Unix timestamp or an RFC 3339 date-time'];
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $mutate
     */
    #[DataProvider('invalidBodies')]
    public function testRejectsInvalidValues(callable $mutate, string $message): void
    {
        $this->expectException(SynthientException::class);
        $this->expectExceptionMessage("synthient: invalid response: {$message}");
        Decoder::decode(IpInfo::class, json_encode($mutate(Fixtures::ipInfo()), JSON_THROW_ON_ERROR));
    }

    public function testErrorsNeverIncludeValues(): void
    {
        $body = Fixtures::ipInfo();
        $body['ip'] = ['secret-value'];
        $e = self::thrown(SynthientException::class, static fn() => Decoder::decode(IpInfo::class, json_encode($body, JSON_THROW_ON_ERROR)));
        self::assertStringNotContainsString('secret-value', $e->getMessage());
    }

    public function testErrorsNeverIncludeMapKeys(): void
    {
        $e = self::thrown(SynthientException::class, static fn() => Decoder::decode(HttpRequestDetails::class, '{"headers":{"x\r\nforged log line":1}}'));

        self::assertSame('synthient: invalid response: headers.* must be a string', $e->getMessage());
    }

    /**
     * @return iterable<array{string}>
     */
    public static function nonIntegerNumbers(): iterable
    {
        yield ['15169.0'];
        yield ['1.5169e4'];
        yield ['99999999999999999999'];
    }

    #[DataProvider('nonIntegerNumbers')]
    public function testRejectsNumbersThatAreNotInt64(string $asn): void
    {
        $json = str_replace('"asn":15169', "\"asn\":{$asn}", json_encode(Fixtures::ipInfo(), JSON_THROW_ON_ERROR));

        $this->expectExceptionMessage('network.asn must be an integer');
        Decoder::decode(IpInfo::class, $json);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidDocuments(): iterable
    {
        yield [''];
        yield ['{"ip":"8.8.8.8"'];
        yield ['{"ip":"8.8.8.8"} {}'];
        yield ['[]'];
        yield ['null'];
        yield ['"8.8.8.8"'];
    }

    #[DataProvider('invalidDocuments')]
    public function testRejectsDocumentsThatAreNotOneObject(string $json): void
    {
        $this->expectException(SynthientException::class);
        Decoder::decode(IpInfo::class, $json);
    }

    public function testReadsUint64AsAString(): void
    {
        $event = static fn(string $id): string => '{"timestamp":1760000000,"tunnel_id":' . $id . ',"domain":"example.com","port":53,"protocol":"dns","meta":{}}';

        self::assertSame('18446744073709551615', Decoder::decode(HeliosDnsEvent::class, $event('18446744073709551615'))->tunnelId);
        self::assertSame('0', Decoder::decode(HeliosDnsEvent::class, $event('0'))->tunnelId);
        foreach (['-1', '-9223372036854775809', '1.5', '"x"', 'true'] as $id) {
            $e = self::thrown(SynthientException::class, static fn() => Decoder::decode(HeliosDnsEvent::class, $event($id)));
            self::assertStringContainsString('tunnel_id must be an unsigned integer', $e->getMessage(), $id);
        }
    }

    public function testIgnoresUnknownFieldsAtEveryLevel(): void
    {
        $body = Fixtures::ipInfo();
        $body['location']['future'] = 1;
        $body['intelligence']['future'] = [1];
        $body['intelligence']['providers'][0]['future'] = ['a' => 'b'];
        $body['intelligence']['devices'][0]['future'] = null;

        self::assertEquals(
            Decoder::decode(IpInfo::class, json_encode(Fixtures::ipInfo(), JSON_THROW_ON_ERROR)),
            Decoder::decode(IpInfo::class, json_encode($body, JSON_THROW_ON_ERROR)),
        );
    }

    public function testRejectsInvalidDateTimes(): void
    {
        foreach (['2026-02-30T00:00:00Z', '2026-09-28 19:04:52Z', '2026-09-28T19:04:52', 'now'] as $value) {
            $body = Fixtures::account();
            $body['api_key']['created_at'] = $value;
            $e = self::thrown(SynthientException::class, static fn() => Decoder::decode(Account::class, json_encode($body, JSON_THROW_ON_ERROR)));
            self::assertStringContainsString('api_key.created_at', $e->getMessage(), $value);
        }
    }

    public function testUnixTimeReadsSecondsAndMilliseconds(): void
    {
        self::assertSame('1970-01-01T00:00:00.000+00:00', Decoder::unixTime(0)->format('Y-m-d\TH:i:s.vP'));
        self::assertSame('2025-10-09T08:53:20.000+00:00', Decoder::unixTime(1_760_000_000)->format('Y-m-d\TH:i:s.vP'));
        self::assertSame('2025-10-09T08:53:20.123+00:00', Decoder::unixTime(1_760_000_000_123)->format('Y-m-d\TH:i:s.vP'));
        self::assertSame('1938-04-24T22:13:19.877+00:00', Decoder::unixTime(-1_000_000_000_123)->format('Y-m-d\TH:i:s.vP'));
    }
}
