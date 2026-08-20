<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The result of repricing a task.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TaskPriority
{
    public function __construct(
        public readonly string $id,
        public readonly float $priority,
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
            priority: (float) ($data['priority'] ?? 0),
        );
    }
}
