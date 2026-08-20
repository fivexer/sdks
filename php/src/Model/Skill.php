<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A skill in the workspace catalogue.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class Skill
{
    public function __construct(
        public readonly string $id,
        public readonly string $key,
        public readonly string $name,
        public readonly ?string $description,
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
            name: (string) ($data['name'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            createdAt: (string) ($data['createdAt'] ?? ''),
        );
    }
}
