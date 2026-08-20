<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The result of the operator override `tasks()->assign()`.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class AssignTaskResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly string $workerId,
        /** null when the task came from the queue */
        public readonly ?string $previousWorkerId,
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
            status: (string) ($data['status'] ?? ''),
            workerId: (string) ($data['workerId'] ?? ''),
            previousWorkerId: isset($data['previousWorkerId']) ? (string) $data['previousWorkerId'] : null,
        );
    }
}
