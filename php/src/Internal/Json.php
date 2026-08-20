<?php

declare(strict_types=1);

namespace Fivexer\SDK\Internal;

/**
 * Shared request-shaping helpers.
 *
 * Every input model serialises through {@see self::compact()}. The API distinguishes an
 * *absent* field from a *null* one: on every partial-update endpoint an absent field keeps its
 * stored value, while an explicit null would clear it. Routing all input models through one
 * call means that rule is implemented — and tested — in one place instead of once per
 * optional field.
 *
 * @internal
 */
final class Json
{
    /**
     * Drop null-valued keys, preserving insertion order.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function compact(array $payload): array
    {
        return \array_filter($payload, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Serialise a list of input models, or null when the list itself was never set.
     *
     * @param list<object>|null $items
     * @return list<array<string, mixed>>|null
     */
    public static function each(?array $items): ?array
    {
        if ($items === null) {
            return null;
        }
        /** @var list<array<string, mixed>> */
        return \array_map(static fn (object $item): array => $item->toArray(), $items);
    }

    /**
     * Parse a list-valued key, treating missing and null alike as empty.
     *
     * @param array<string, mixed> $data
     * @param callable(array<string, mixed>): object $parse
     * @return list<object>
     */
    public static function parseEach(array $data, string $key, callable $parse): array
    {
        /** @var list<object> */
        return \array_map($parse, \array_values($data[$key] ?? []));
    }

    /**
     * Parse a nullable list-valued key, preserving the null/empty distinction.
     *
     * @param array<string, mixed> $data
     * @param callable(array<string, mixed>): object $parse
     * @return list<object>|null
     */
    public static function parseEachOrNull(array $data, string $key, callable $parse): ?array
    {
        if (!isset($data[$key]) || $data[$key] === null) {
            return null;
        }
        /** @var list<object> */
        return \array_map($parse, \array_values($data[$key]));
    }
}
