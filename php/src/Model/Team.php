<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A team: a routing primitive, not a label — matching sees the derived `tag`.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class Team
{
    public function __construct(
        public readonly string $id,
        public readonly string $key,
        /** The routing tag the server derived from the key. */
        public readonly string $tag,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $color,
        /** ISO-8601 */
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            key: (string) ($data['key'] ?? ''),
            tag: (string) ($data['tag'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            color: isset($data['color']) ? (string) $data['color'] : null,
            createdAt: (string) ($data['createdAt'] ?? ''),
        );
    }
}
