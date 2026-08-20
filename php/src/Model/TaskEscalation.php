<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Result of advancing the escalation ladder. `escalated: false, parked: true` is the end of the ladder — the task has left matching, and this flag is the only thing that says so.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TaskEscalation
{
    public function __construct(
        public readonly string $id,
        public readonly bool $escalated,
        public readonly bool $parked,
        public readonly int $escalationLevel,
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
            escalated: (bool) ($data['escalated'] ?? false),
            parked: (bool) ($data['parked'] ?? false),
            escalationLevel: (int) ($data['escalationLevel'] ?? 0),
        );
    }
}
