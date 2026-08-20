<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Outcome of handing a task to a crew member.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class SupervisorAssignResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $workerId,
        public readonly string $status,
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
            workerId: (string) ($data['workerId'] ?? ''),
            status: (string) ($data['status'] ?? ''),
        );
    }
}
