<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/** Result of GET /v1/stats: workspace counts, worker total, and the monthly meter. */
final class WorkspaceStats
{
    /**
     * @param array<string, int> $tasks
     */
    public function __construct(
        public readonly string $plan,
        public readonly array $tasks,
        public readonly int $workers,
        public readonly string $meterPeriod,
        public readonly int $meterMatchedTasks,
        public readonly int $meterIncludedTasksPerMonth,
        /** Queue depth and per-worker load; null on data-plane-only deployments */
        public readonly ?QueueStats $queue = null,
        /** The live balancer policy applied to bulk matching passes */
        public readonly ?MatchingPolicy $matching = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $meter = $data['meter'] ?? [];
        /** @var array<string, int> $tasks */
        $tasks = $data['tasks'] ?? [];
        return new self(
            plan: (string) ($data['plan'] ?? ''),
            tasks: \array_map('intval', $tasks),
            workers: (int) ($data['workers'] ?? 0),
            meterPeriod: (string) ($meter['period'] ?? ''),
            meterMatchedTasks: (int) ($meter['matchedTasks'] ?? 0),
            meterIncludedTasksPerMonth: (int) ($meter['includedTasksPerMonth'] ?? 0),
            queue: isset($data['queue']) ? QueueStats::fromArray($data['queue']) : null,
            matching: isset($data['matching']) ? MatchingPolicy::fromArray($data['matching']) : null,
        );
    }
}
