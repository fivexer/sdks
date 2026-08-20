<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * A worker own metrics for today.
 *
 * Response model: built from a decoded JSON array and read-only thereafter.
 */
final class WorkerMetricsToday
{
    public function __construct(
        public readonly string $workerId,
        public readonly string $since,
        public readonly int $completedTasks,
        public readonly int $breakCount,
        public readonly int $totalBreakMs,
        public readonly int $longestBreakMs,
        public readonly int $workingMs,
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
            since: (string) ($data['since'] ?? ''),
            completedTasks: (int) ($data['completedTasks'] ?? 0),
            breakCount: (int) ($data['breakCount'] ?? 0),
            totalBreakMs: (int) ($data['totalBreakMs'] ?? 0),
            longestBreakMs: (int) ($data['longestBreakMs'] ?? 0),
            workingMs: (int) ($data['workingMs'] ?? 0),
        );
    }
}
