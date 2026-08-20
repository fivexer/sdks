<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * The state of one step within a run.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkflowRunStep
{
    public function __construct(
        public readonly string $stepId,
        public readonly string $name,
        public readonly string $taskType,
        public readonly string $state,
        public readonly ?string $taskId,
        public readonly ?string $workerId,
        public readonly ?int $completedAt,
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
            name: (string) ($data['name'] ?? ''),
            taskType: (string) ($data['taskType'] ?? ''),
            state: (string) ($data['state'] ?? ''),
            taskId: isset($data['taskId']) ? (string) $data['taskId'] : null,
            workerId: isset($data['workerId']) ? (string) $data['workerId'] : null,
            completedAt: isset($data['completedAt']) ? (int) $data['completedAt'] : null,
            result: $data['result'] ?? null,
        );
    }
}
