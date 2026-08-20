<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * A running (or finished) instance of a workflow definition.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkflowRun
{
    public function __construct(
        public readonly string $id,
        public readonly string $workflowId,
        public readonly string $status,
        public readonly string $initiatorWorkerId,
        public readonly int $definitionVersion,
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly ?string $currentStepId,
        public readonly ?string $currentTaskId,
        /** @var array<string, mixed> */
        public readonly array $context,
        /** @var list<WorkflowRunHistoryEntry> */
        public readonly array $history,
        /** @var list<ParallelBranch>|null */
        public readonly ?array $parallelBranches,
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
            workflowId: (string) ($data['workflowId'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            initiatorWorkerId: (string) ($data['initiatorWorkerId'] ?? ''),
            definitionVersion: (int) ($data['definitionVersion'] ?? 0),
            createdAt: (int) ($data['createdAt'] ?? 0),
            updatedAt: (int) ($data['updatedAt'] ?? 0),
            currentStepId: isset($data['currentStepId']) ? (string) $data['currentStepId'] : null,
            currentTaskId: isset($data['currentTaskId']) ? (string) $data['currentTaskId'] : null,
            context: $data['context'] ?? [],
            history: Json::parseEach($data, 'history', [WorkflowRunHistoryEntry::class, 'fromArray']),
            parallelBranches: Json::parseEachOrNull($data, 'parallelBranches', [ParallelBranch::class, 'fromArray']),
        );
    }
}
