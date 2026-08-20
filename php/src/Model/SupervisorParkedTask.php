<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A parked task as the board shows it — enough to decide on, not the whole task.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SupervisorParkedTask
{
    public function __construct(
        public readonly string $id,
        /** @var list<string> */
        public readonly array $tags,
        public readonly ?float $priority,
        public readonly ?int $createdAt,
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
            tags: \array_values(\array_map(strval(...), $data['tags'] ?? [])),
            priority: isset($data['priority']) ? (float) $data['priority'] : null,
            createdAt: isset($data['createdAt']) ? (int) $data['createdAt'] : null,
        );
    }
}
