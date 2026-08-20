<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One crew member's current load.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SupervisorCrewMember
{
    public function __construct(
        public readonly string $workerId,
        public readonly int $backlog,
        public readonly bool $available,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            workerId: (string) ($data['workerId'] ?? ''),
            backlog: (int) ($data['backlog'] ?? 0),
            available: (bool) ($data['available'] ?? false),
        );
    }
}
