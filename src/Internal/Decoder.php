<?php

declare(strict_types=1);

namespace Synthient\Internal;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use ReflectionClass;
use ReflectionNamedType;
use Synthient\Exception\SynthientException;

/**
 * Builds models from JSON by checking each value against the model constructor's types.
 *
 * Required fields must be present with the exact JSON type, so "15169", 15169.0, and null
 * are all rejected where an integer is expected. Nullable parameters accept an absent or
 * null field. Unknown fields are ignored. Integer timestamps of at least 1e12 are read as
 * milliseconds and smaller ones as seconds; string timestamps must be RFC 3339. Errors name
 * the field path and never include values or map keys.
 *
 * JSON is decoded into arrays rather than objects, because PHP objects cannot hold every
 * valid key, such as one starting with a NUL byte. An empty object and an empty list are
 * then indistinguishable, so each is accepted where the other is expected.
 *
 * @internal
 */
final class Decoder
{
    private const string RFC3339 = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,9})?(Z|[+-]\d{2}:\d{2})$/i';

    /** @var array<class-string, list<array{param: string, key: string, type: string, nullable: bool}>> */
    private static array $fields = [];

    private static ?DateTimeZone $utc = null;

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public static function decode(string $class, string $json): object
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $e) {
            throw new SynthientException('synthient: response is not valid JSON', 0, $e);
        }

        return self::object($class, $data, '');
    }

    public static function unixTime(int $value): DateTimeImmutable
    {
        if (abs($value) < 1_000_000_000_000) {
            return new DateTimeImmutable("@{$value}");
        }

        return new DateTimeImmutable(\sprintf('@%d.%03d', intdiv($value, 1000), abs($value % 1000)));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function object(string $class, mixed $data, string $path): object
    {
        if (!\is_array($data) || ($data !== [] && array_is_list($data))) {
            throw self::invalid($path === '' ? 'response' : $path, 'an object');
        }
        $args = [];
        foreach (self::$fields[$class] ??= self::fields($class) as $field) {
            $at = $path === '' ? $field['key'] : "{$path}.{$field['key']}";
            $value = $data[$field['key']] ?? null;
            if ($value === null && !$field['nullable']) {
                throw new SynthientException("synthient: invalid response: {$at} is missing");
            }
            $args[$field['param']] = $value === null ? null : self::value($field['type'], $value, $at);
        }

        return new $class(...$args);
    }

    private static function value(string $type, mixed $value, string $path): mixed
    {
        return match ($type) {
            'string' => \is_string($value) ? $value : throw self::invalid($path, 'a string'),
            'int' => \is_int($value) ? $value : throw self::invalid($path, 'an integer'),
            'uint64' => self::uint64($value, $path),
            'float' => \is_int($value) || \is_float($value) ? (float) $value : throw self::invalid($path, 'a number'),
            'bool' => \is_bool($value) ? $value : throw self::invalid($path, 'a boolean'),
            DateTimeImmutable::class => self::time($value, $path),
            default => self::compound($type, $value, $path),
        };
    }

    private static function compound(string $type, mixed $value, string $path): mixed
    {
        if (str_starts_with($type, 'list<')) {
            if (!\is_array($value) || !array_is_list($value)) {
                throw self::invalid($path, 'a list');
            }
            $element = substr($type, 5, -1);
            $items = [];
            foreach ($value as $index => $item) {
                $items[] = self::value($element, $item, "{$path}[{$index}]");
            }

            return $items;
        }
        if (str_starts_with($type, 'map<')) {
            if (!\is_array($value)) {
                throw self::invalid($path, 'an object');
            }
            $element = substr($type, 4, -1);
            $items = [];
            foreach ($value as $key => $item) {
                $items[$key] = self::value($element, $item, "{$path}.*");
            }

            return $items;
        }
        if (!class_exists($type)) {
            throw new \LogicException("cannot decode JSON into {$type}");
        }

        return self::object($type, $value, $path);
    }

    /**
     * Reads an unsigned 64-bit integer as a decimal string, because it can exceed PHP_INT_MAX.
     */
    private static function uint64(mixed $value, string $path): string
    {
        if (\is_int($value) && $value >= 0) {
            return (string) $value;
        }
        if (\is_string($value) && preg_match('/^\d{1,20}$/', $value) === 1) {
            return $value;
        }

        throw self::invalid($path, 'an unsigned integer');
    }

    private static function time(mixed $value, string $path): DateTimeImmutable
    {
        if (\is_int($value)) {
            return self::unixTime($value);
        }
        if (!\is_string($value) || preg_match(self::RFC3339, $value) !== 1) {
            throw self::invalid($path, 'a Unix timestamp or an RFC 3339 date-time');
        }
        $time = date_create_immutable($value);
        if ($time === false || DateTimeImmutable::getLastErrors() !== false) {
            throw self::invalid($path, 'a Unix timestamp or an RFC 3339 date-time');
        }

        return $time->setTimezone(self::$utc ??= new DateTimeZone('UTC'));
    }

    /**
     * @param class-string $class
     *
     * @return list<array{param: string, key: string, type: string, nullable: bool}>
     */
    public static function fields(string $class): array
    {
        $constructor = (new ReflectionClass($class))->getConstructor();
        $fields = [];
        foreach ($constructor?->getParameters() ?? [] as $param) {
            $type = $param->getType();
            if (!$type instanceof ReflectionNamedType) {
                throw new \LogicException("{$class}::\${$param->getName()} needs a single native type");
            }
            $wire = ($param->getAttributes(Wire::class)[0] ?? null)?->newInstance();
            $fields[] = [
                'param' => $param->getName(),
                'key' => $wire->name ?? strtolower((string) preg_replace('/[A-Z]/', '_$0', $param->getName())),
                'type' => match (true) {
                    $wire?->type !== null => $wire->type,
                    $wire?->list !== null => "list<{$wire->list}>",
                    $wire?->map !== null => "map<{$wire->map}>",
                    default => $type->getName(),
                },
                'nullable' => $type->allowsNull(),
            ];
        }

        return $fields;
    }

    private static function invalid(string $path, string $expected): SynthientException
    {
        return new SynthientException("synthient: invalid response: {$path} must be {$expected}");
    }
}
