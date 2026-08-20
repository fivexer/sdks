<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * One completed step in a run history.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkflowRunHistoryEntry
{
    public function __construct(
        public readonly string $stepId,
        public readonly string $taskId,
        public readonly string $workerId,
        public readonly int $completedAt,
        /** @var array<string, mixed>|null */
        public readonly ?array $result,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            stepId: (string) ($data['stepId'] ?? ''),
            taskId: (string) ($data['taskId'] ?? ''),
            workerId: (string) ($data['workerId'] ?? ''),
            completedAt: (int) ($data['completedAt'] ?? 0),
            result: $data['result'] ?? null,
        );
    }
}
