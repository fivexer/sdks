<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One worker's membership of a team.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class TeamMember
{
    public function __construct(
        public readonly string $workerId,
        /** 'member' | 'lead' */
        public readonly string $role,
        /** ISO-8601 */
        public readonly string $addedAt,
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
            role: (string) ($data['role'] ?? ''),
            addedAt: (string) ($data['addedAt'] ?? ''),
        );
    }
}
